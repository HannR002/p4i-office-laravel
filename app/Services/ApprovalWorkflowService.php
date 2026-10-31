<?php

namespace App\Services;

use App\Exceptions\StaleRevisionException;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\AuditEvent;
use App\Models\Letter;
use App\Models\LetterStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ApprovalWorkflowService
{
    public function __construct(
        protected MandateValidationService $mandateService
    ) {}

    /**
     * Mengajukan draf surat terkini untuk ditelaah oleh reviewer berwenang.
     * Transisi: DRAFT -> SUBMITTED.
     * M2-FR-01, M2-FR-02, AP2-01.
     */
    public function submit(User $author, Letter $letter, array $data = []): ApprovalRequest
    {
        // 1. Verifikasi kepemilikan dan hak akses
        if ($author->organization_id !== $letter->organization_id) {
            throw new AccessDeniedHttpException('Akses lintas organisasi ditolak.');
        }

        if ($author->status !== 'ACTIVE') {
            throw new AccessDeniedHttpException('Pengguna tidak aktif.');
        }

        if ($letter->author_id !== $author->id) {
            throw new AccessDeniedHttpException('Hanya pembuat draf yang berhak mengajukan draf untuk pemeriksaan.');
        }

        // 2. Pemeriksaan Idempotensi: jika sudah ada request dengan idempotency_key yang sama
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = ApprovalRequest::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($author, $letter, $data, $idempotencyKey) {
            // Kunci baris surat untuk mencegah submit ganda secara paralel
            $lockedLetter = Letter::where('id', $letter->id)->lockForUpdate()->first();

            // Validasi status surat saat ini
            if ($lockedLetter->status !== 'DRAFT') {
                // Jika sudah SUBMITTED dan memiliki permohonan aktif, periksa idempotensi revisi
                if ($lockedLetter->status === 'SUBMITTED') {
                    $activeRequest = ApprovalRequest::where('letter_id', $lockedLetter->id)
                        ->where('status', 'PENDING')
                        ->first();
                    if ($activeRequest) {
                        return $activeRequest;
                    }
                }

                throw ValidationException::withMessages([
                    'status' => "Surat tidak dapat diajukan karena berstatus: {$lockedLetter->status}. Draf harus berstatus DRAFT.",
                ]);
            }

            // Ambil revisi terkini
            $latestRevision = $lockedLetter->latestRevision;
            if (! $latestRevision) {
                throw ValidationException::withMessages([
                    'revision' => 'Draf surat belum memiliki riwayat revisi yang sah.',
                ]);
            }

            // Validasi keaktifan templat
            if ($lockedLetter->template && ! $lockedLetter->template->is_active) {
                throw ValidationException::withMessages([
                    'template' => 'Templat surat yang digunakan sedang tidak aktif.',
                ]);
            }

            // Perbarui status surat menjadi SUBMITTED
            $lockedLetter->update(['status' => 'SUBMITTED']);

            // Buat record permohonan persetujuan
            $approvalRequest = ApprovalRequest::create([
                'organization_id' => $lockedLetter->organization_id,
                'letter_id' => $lockedLetter->id,
                'revision_id' => $latestRevision->id,
                'revision_version' => $latestRevision->version,
                'content_sha256' => $latestRevision->content_sha256,
                'submitted_by' => $author->id,
                'submitted_at' => now(),
                'status' => 'PENDING',
                'submission_notes' => $data['submission_notes'] ?? null,
                'workflow_version' => 1,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Catat riwayat status surat
            LetterStatusHistory::create([
                'letter_id' => $lockedLetter->id,
                'revision_id' => $latestRevision->id,
                'from_status' => 'DRAFT',
                'to_status' => 'SUBMITTED',
                'actor_id' => $author->id,
                'reason' => $data['submission_notes'] ?? 'Draf surat diajukan untuk pemeriksaan.',
            ]);

            // Catat peristiwa audit
            AuditEvent::create([
                'organization_id' => $lockedLetter->organization_id,
                'actor_id' => $author->id,
                'event_type' => 'LETTER_SUBMITTED',
                'entity_type' => 'LETTER',
                'entity_id' => $lockedLetter->id,
                'event_metadata' => [
                    'revision_version' => $latestRevision->version,
                    'content_sha256' => $latestRevision->content_sha256,
                    'approval_request_id' => $approvalRequest->id,
                ],
            ]);

            return $approvalRequest;
        });
    }

    /**
     * Mengklaim / memulai proses telaah draf surat oleh reviewer berwenang.
     * Transisi: SUBMITTED -> IN_REVIEW.
     * M2-FR-04, AP2-04.
     */
    public function claimReview(User $reviewer, Letter $letter): ApprovalRequest
    {
        // 1. Validasi mandat reviewer
        $this->mandateService->validateMandate($reviewer, $letter, 'REVIEW');

        return DB::transaction(function () use ($reviewer, $letter) {
            // Kunci baris surat dan permohonan persetujuan
            $lockedLetter = Letter::where('id', $letter->id)->lockForUpdate()->first();

            if ($lockedLetter->status !== 'SUBMITTED') {
                if ($lockedLetter->status === 'IN_REVIEW') {
                    $activeReq = ApprovalRequest::where('letter_id', $lockedLetter->id)
                        ->where('status', 'IN_REVIEW')
                        ->first();
                    if ($activeReq) {
                        return $activeReq;
                    }
                }

                throw ValidationException::withMessages([
                    'status' => "Surat tidak dalam status SUBMITTED (Status saat ini: {$lockedLetter->status}).",
                ]);
            }

            $approvalRequest = ApprovalRequest::where('letter_id', $lockedLetter->id)
                ->where('status', 'PENDING')
                ->lockForUpdate()
                ->first();

            if (! $approvalRequest) {
                throw ValidationException::withMessages([
                    'approval_request' => 'Permohonan persetujuan tidak ditemukan atau telah diproses.',
                ]);
            }

            // Perbarui status surat dan permohonan
            $lockedLetter->update(['status' => 'IN_REVIEW']);
            $approvalRequest->update([
                'status' => 'IN_REVIEW',
                'claimed_by' => $reviewer->id,
                'claimed_at' => now(),
            ]);

            // Catat riwayat status
            LetterStatusHistory::create([
                'letter_id' => $lockedLetter->id,
                'revision_id' => $approvalRequest->revision_id,
                'from_status' => 'SUBMITTED',
                'to_status' => 'IN_REVIEW',
                'actor_id' => $reviewer->id,
                'reason' => 'Reviewer memulai proses pemeriksaan draf surat.',
            ]);

            // Catat audit log
            AuditEvent::create([
                'organization_id' => $lockedLetter->organization_id,
                'actor_id' => $reviewer->id,
                'event_type' => 'REVIEW_STARTED',
                'entity_type' => 'LETTER',
                'entity_id' => $lockedLetter->id,
                'event_metadata' => [
                    'approval_request_id' => $approvalRequest->id,
                    'reviewer_id' => $reviewer->id,
                ],
            ]);

            return $approvalRequest;
        });
    }

    /**
     * Menyetujui revisi surat tertentu secara mengikat.
     * Transisi: IN_REVIEW / SUBMITTED -> APPROVED.
     * M2-FR-05, M2-FR-08, AP2-04, AP2-05, AP2-10, AP2-18.
     */
    public function approve(User $reviewer, Letter $letter, array $data = []): ApprovalStep
    {
        // 1. Validasi mandat persetujuan (wajib can_approve = true)
        $mandate = $this->mandateService->validateMandate($reviewer, $letter, 'APPROVE');

        // 2. Pemeriksaan Idempotensi
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existingStep = ApprovalStep::where('idempotency_key', $idempotencyKey)->first();
            if ($existingStep) {
                return $existingStep;
            }
        }

        return DB::transaction(function () use ($reviewer, $letter, $mandate, $data, $idempotencyKey) {
            // Kunci baris surat dan permohonan persetujuan
            $lockedLetter = Letter::where('id', $letter->id)->lockForUpdate()->first();

            if (! in_array($lockedLetter->status, ['SUBMITTED', 'IN_REVIEW'], true)) {
                throw ValidationException::withMessages([
                    'status' => "Surat tidak dapat disetujui karena berstatus: {$lockedLetter->status}.",
                ]);
            }

            $approvalRequest = ApprovalRequest::where('letter_id', $lockedLetter->id)
                ->whereIn('status', ['PENDING', 'IN_REVIEW'])
                ->lockForUpdate()
                ->first();

            if (! $approvalRequest) {
                throw ValidationException::withMessages([
                    'approval_request' => 'Permohonan persetujuan aktif tidak ditemukan atau telah memiliki keputusan final.',
                ]);
            }

            // 3. Verifikasi konsistensi versi revisi dan hash konten (Gate AP2-05)
            $expectedVersion = $data['expected_version'] ?? null;
            if ($expectedVersion !== null && (int) $expectedVersion !== $lockedLetter->current_revision_no) {
                throw new StaleRevisionException("Versi revisi surat telah diperbarui (Versi surat: {$lockedLetter->current_revision_no}, versi disetujui: {$expectedVersion}). Keputusan dibatalkan (409 Conflict).");
            }

            $expectedHash = $data['expected_sha256'] ?? null;
            if ($expectedHash !== null && $expectedHash !== $approvalRequest->content_sha256) {
                throw new StaleRevisionException('Hash integritas konten revisi tidak cocok (Stale revision content hash). Keputusan dibatalkan (409 Conflict).');
            }

            // 4. Perbarui status surat menjadi APPROVED
            // PENTING: official_number TETAP NULL! official_numbering_active = false!
            $fromStatus = $lockedLetter->status;
            $lockedLetter->update([
                'status' => 'APPROVED',
                'official_number' => null, // Safety Guard: strictly NULL
            ]);

            $approvalRequest->update(['status' => 'APPROVED']);

            // 5. Buat langkah persetujuan yang mengikat
            $step = ApprovalStep::create([
                'approval_request_id' => $approvalRequest->id,
                'letter_id' => $lockedLetter->id,
                'revision_id' => $approvalRequest->revision_id,
                'step_no' => 1,
                'workflow_version' => $approvalRequest->workflow_version,
                'reviewer_id' => $reviewer->id,
                'mandate_id' => $mandate->id,
                'decision' => 'APPROVED',
                'decision_content_sha256' => $approvalRequest->content_sha256,
                'comments' => $data['comments'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'decided_at' => now(),
            ]);

            // 6. Catat riwayat status
            LetterStatusHistory::create([
                'letter_id' => $lockedLetter->id,
                'revision_id' => $approvalRequest->revision_id,
                'from_status' => $fromStatus,
                'to_status' => 'APPROVED',
                'actor_id' => $reviewer->id,
                'reason' => $data['comments'] ?? 'Draf surat disetujui oleh pejabat pemeriksa.',
            ]);

            // 7. Catat audit trail append-only
            AuditEvent::create([
                'organization_id' => $lockedLetter->organization_id,
                'actor_id' => $reviewer->id,
                'event_type' => 'LETTER_APPROVED',
                'entity_type' => 'LETTER',
                'entity_id' => $lockedLetter->id,
                'event_metadata' => [
                    'approval_request_id' => $approvalRequest->id,
                    'approval_step_id' => $step->id,
                    'revision_version' => $approvalRequest->revision_version,
                    'content_sha256' => $approvalRequest->content_sha256,
                    'mandate_id' => $mandate->id,
                    'decree_number' => $mandate->decree_number,
                ],
            ]);

            return $step;
        });
    }

    /**
     * Mengembalikan draf surat untuk direvisi oleh staf (Permintaan Perubahan).
     * Transisi: IN_REVIEW / SUBMITTED -> CHANGES_REQUESTED.
     * M2-FR-07, AP2-08.
     */
    public function requestChanges(User $reviewer, Letter $letter, array $data): ApprovalStep
    {
        // 1. Validasi mandat reviewer
        $mandate = $this->mandateService->validateMandate($reviewer, $letter, 'REVIEW');

        // 2. Alasan wajib ada (non-empty reason)
        $reason = trim($data['reason'] ?? $data['comments'] ?? '');
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pengembalian draf untuk revisi wajib dicantumkan.',
            ]);
        }

        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existingStep = ApprovalStep::where('idempotency_key', $idempotencyKey)->first();
            if ($existingStep) {
                return $existingStep;
            }
        }

        return DB::transaction(function () use ($reviewer, $letter, $mandate, $reason, $idempotencyKey) {
            $lockedLetter = Letter::where('id', $letter->id)->lockForUpdate()->first();

            if (! in_array($lockedLetter->status, ['SUBMITTED', 'IN_REVIEW'], true)) {
                throw ValidationException::withMessages([
                    'status' => "Surat tidak dapat dikembalikan untuk revisi karena berstatus: {$lockedLetter->status}.",
                ]);
            }

            $approvalRequest = ApprovalRequest::where('letter_id', $lockedLetter->id)
                ->whereIn('status', ['PENDING', 'IN_REVIEW'])
                ->lockForUpdate()
                ->first();

            if (! $approvalRequest) {
                throw ValidationException::withMessages([
                    'approval_request' => 'Permohonan persetujuan aktif tidak ditemukan.',
                ]);
            }

            $fromStatus = $lockedLetter->status;
            $lockedLetter->update(['status' => 'CHANGES_REQUESTED']);
            $approvalRequest->update(['status' => 'CHANGES_REQUESTED']);

            $step = ApprovalStep::create([
                'approval_request_id' => $approvalRequest->id,
                'letter_id' => $lockedLetter->id,
                'revision_id' => $approvalRequest->revision_id,
                'step_no' => 1,
                'workflow_version' => $approvalRequest->workflow_version,
                'reviewer_id' => $reviewer->id,
                'mandate_id' => $mandate->id,
                'decision' => 'CHANGES_REQUESTED',
                'decision_content_sha256' => $approvalRequest->content_sha256,
                'comments' => $reason,
                'idempotency_key' => $idempotencyKey,
                'decided_at' => now(),
            ]);

            LetterStatusHistory::create([
                'letter_id' => $lockedLetter->id,
                'revision_id' => $approvalRequest->revision_id,
                'from_status' => $fromStatus,
                'to_status' => 'CHANGES_REQUESTED',
                'actor_id' => $reviewer->id,
                'reason' => $reason,
            ]);

            AuditEvent::create([
                'organization_id' => $lockedLetter->organization_id,
                'actor_id' => $reviewer->id,
                'event_type' => 'CHANGES_REQUESTED',
                'entity_type' => 'LETTER',
                'entity_id' => $lockedLetter->id,
                'event_metadata' => [
                    'approval_request_id' => $approvalRequest->id,
                    'approval_step_id' => $step->id,
                    'reason' => $reason,
                ],
            ]);

            return $step;
        });
    }

    /**
     * Menolak draf surat secara definitif dengan alasan yang mengikat.
     * Transisi: IN_REVIEW / SUBMITTED -> REJECTED.
     * M2-FR-07, AP2-09.
     */
    public function reject(User $reviewer, Letter $letter, array $data): ApprovalStep
    {
        // 1. Validasi mandat reviewer
        $mandate = $this->mandateService->validateMandate($reviewer, $letter, 'REVIEW');

        // 2. Alasan penolakan wajib diisi
        $reason = trim($data['reason'] ?? $data['comments'] ?? '');
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan surat wajib dicantumkan.',
            ]);
        }

        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existingStep = ApprovalStep::where('idempotency_key', $idempotencyKey)->first();
            if ($existingStep) {
                return $existingStep;
            }
        }

        return DB::transaction(function () use ($reviewer, $letter, $mandate, $reason, $idempotencyKey) {
            $lockedLetter = Letter::where('id', $letter->id)->lockForUpdate()->first();

            if (! in_array($lockedLetter->status, ['SUBMITTED', 'IN_REVIEW'], true)) {
                throw ValidationException::withMessages([
                    'status' => "Surat tidak dapat ditolak karena berstatus: {$lockedLetter->status}.",
                ]);
            }

            $approvalRequest = ApprovalRequest::where('letter_id', $lockedLetter->id)
                ->whereIn('status', ['PENDING', 'IN_REVIEW'])
                ->lockForUpdate()
                ->first();

            if (! $approvalRequest) {
                throw ValidationException::withMessages([
                    'approval_request' => 'Permohonan persetujuan aktif tidak ditemukan.',
                ]);
            }

            $fromStatus = $lockedLetter->status;
            $lockedLetter->update(['status' => 'REJECTED']);
            $approvalRequest->update(['status' => 'REJECTED']);

            $step = ApprovalStep::create([
                'approval_request_id' => $approvalRequest->id,
                'letter_id' => $lockedLetter->id,
                'revision_id' => $approvalRequest->revision_id,
                'step_no' => 1,
                'workflow_version' => $approvalRequest->workflow_version,
                'reviewer_id' => $reviewer->id,
                'mandate_id' => $mandate->id,
                'decision' => 'REJECTED',
                'decision_content_sha256' => $approvalRequest->content_sha256,
                'comments' => $reason,
                'idempotency_key' => $idempotencyKey,
                'decided_at' => now(),
            ]);

            LetterStatusHistory::create([
                'letter_id' => $lockedLetter->id,
                'revision_id' => $approvalRequest->revision_id,
                'from_status' => $fromStatus,
                'to_status' => 'REJECTED',
                'actor_id' => $reviewer->id,
                'reason' => $reason,
            ]);

            AuditEvent::create([
                'organization_id' => $lockedLetter->organization_id,
                'actor_id' => $reviewer->id,
                'event_type' => 'LETTER_REJECTED',
                'entity_type' => 'LETTER',
                'entity_id' => $lockedLetter->id,
                'event_metadata' => [
                    'approval_request_id' => $approvalRequest->id,
                    'approval_step_id' => $step->id,
                    'reason' => $reason,
                ],
            ]);

            return $step;
        });
    }
}
