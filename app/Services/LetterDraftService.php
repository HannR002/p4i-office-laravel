<?php

namespace App\Services;

use App\Exceptions\StaleRevisionException;
use App\Models\AuditEvent;
use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterStatusHistory;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class LetterDraftService
{
    public function __construct(
        protected TemplateEngineService $templateEngine
    ) {}

    /**
     * Membuat draf surat baru beserta revisi v1 secara atomik dalam satu transaksi.
     *
     * @param User $author Pengguna pembuat surat
     * @param array<string, mixed> $data Data surat dan formulir
     * @return Letter Surat yang berhasil dibuat
     * @throws ValidationException
     */
    public function createDraft(User $author, array $data): Letter
    {
        // 1. Validasi jenis surat
        $letterType = LetterType::where('id', $data['letter_type_id'])
            ->where('organization_id', $author->organization_id)
            ->first();

        if (!$letterType || !$letterType->is_active) {
            throw ValidationException::withMessages([
                'letter_type_id' => 'Jenis surat tidak valid atau sedang dinonaktifkan.',
            ]);
        }

        // 2. Validasi templat surat
        $template = LetterTemplate::where('id', $data['template_id'])
            ->where('organization_id', $author->organization_id)
            ->where('letter_type_id', $letterType->id)
            ->first();

        if (!$template || !$template->is_active) {
            throw ValidationException::withMessages([
                'template_id' => 'Templat surat tidak ditemukan atau sedang tidak aktif.',
            ]);
        }

        // 3. Validasi klasifikasi keamanan
        $classification = strtoupper($data['classification'] ?? 'INTERNAL');
        if (!in_array($classification, ['INTERNAL', 'TERBATAS', 'RAHASIA'], true)) {
            throw ValidationException::withMessages([
                'classification' => 'Klasifikasi surat harus salah satu dari: INTERNAL, TERBATAS, atau RAHASIA.',
            ]);
        }

        // 4. Susun konten terstruktur
        $formVariables = $data['variables'] ?? [];
        $renderedHtml = $this->templateEngine->render($template->content_template, $formVariables);

        $content = [
            'variables' => $formVariables,
            'rendered_body' => $renderedHtml,
            'template_title' => $template->title,
            'template_version' => $template->version,
        ];

        $contentJson = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $contentHash = hash('sha256', $contentJson);

        // 5. Eksekusi transaksi atomik
        return DB::transaction(function () use ($author, $data, $letterType, $template, $classification, $content, $contentHash) {
            $letter = Letter::create([
                'organization_id' => $author->organization_id,
                'unit_id' => $data['unit_id'] ?? $author->unit_id,
                'letter_type_id' => $letterType->id,
                'template_id' => $template->id,
                'author_id' => $author->id,
                'subject' => trim($data['subject']),
                'recipient_name' => trim($data['recipient_name']),
                'classification' => $classification,
                'planned_date' => $data['planned_date'],
                'status' => 'DRAFT',
                'current_revision_no' => 1,
                'official_number' => null, // SAFETY: Mutlak NULL di Sprint M1
            ]);

            $revision = LetterRevision::create([
                'letter_id' => $letter->id,
                'version' => 1,
                'template_id' => $template->id,
                'subject' => $letter->subject,
                'recipient_name' => $letter->recipient_name,
                'classification' => $letter->classification,
                'planned_date' => $letter->planned_date,
                'content' => $content,
                'content_sha256' => $contentHash,
                'change_summary' => 'Inisiasi draf surat baru (v1)',
                'created_by' => $author->id,
                'created_at' => now(),
            ]);

            LetterStatusHistory::create([
                'letter_id' => $letter->id,
                'revision_id' => $revision->id,
                'from_status' => 'INIT',
                'to_status' => 'DRAFT',
                'actor_id' => $author->id,
                'reason' => 'Pembuatan draf surat awal',
            ]);

            AuditEvent::create([
                'organization_id' => $author->organization_id,
                'actor_id' => $author->id,
                'event_type' => 'LETTER_CREATED',
                'entity_type' => 'LETTER',
                'entity_id' => $letter->id,
                'event_metadata' => [
                    'version' => 1,
                    'subject' => $letter->subject,
                    'classification' => $letter->classification,
                    'content_sha256' => $contentHash,
                ],
            ]);

            return $letter->load(['revisions', 'letterType', 'template', 'author']);
        });
    }

    /**
     * Memperbarui draf dengan menghasilkan revisi baru (v2, v3, dst.) secara append-only.
     * Menggunakan Pessimistic Row Locking dan Optimistic Revision Token untuk mencegah lost update.
     *
     * @param User $editor Pengguna yang melakukan revisi
     * @param Letter $letter Surat yang hendak direvisi
     * @param array<string, mixed> $data Data revisi baru
     * @param int $expectedRevisionNo Nomor revisi yang dijadikan acuan dasar pengguna
     * @return LetterRevision Rekaman revisi baru yang berhasil ditambahkan
     * @throws StaleRevisionException
     * @throws ValidationException
     */
    public function reviseDraft(User $editor, Letter $letter, array $data, int $expectedRevisionNo): LetterRevision
    {
        return DB::transaction(function () use ($editor, $letter, $data, $expectedRevisionNo) {
            // Pessimistic Row Locking pada level baris InnoDB
            $lockedLetter = Letter::where('id', $letter->id)
                ->where('organization_id', $editor->organization_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLetter->status !== 'DRAFT') {
                throw new InvalidArgumentException('Hanya surat dengan status DRAFT yang dapat direvisi.');
            }

            // Deteksi Stale Revision / Lost Update
            if ($lockedLetter->current_revision_no !== $expectedRevisionNo) {
                throw new StaleRevisionException(
                    "Draf telah diperbarui oleh pengguna lain (versi saat ini: v{$lockedLetter->current_revision_no}). Silakan muat ulang halaman."
                );
            }

            // Validasi templat (jika diubah)
            $templateId = $data['template_id'] ?? $lockedLetter->template_id;
            $template = LetterTemplate::where('id', $templateId)
                ->where('organization_id', $editor->organization_id)
                ->first();

            if (!$template || !$template->is_active) {
                throw ValidationException::withMessages([
                    'template_id' => 'Templat surat tidak ditemukan atau sedang tidak aktif.',
                ]);
            }

            $classification = strtoupper($data['classification'] ?? $lockedLetter->classification);
            if (!in_array($classification, ['INTERNAL', 'TERBATAS', 'RAHASIA'], true)) {
                throw ValidationException::withMessages([
                    'classification' => 'Klasifikasi surat tidak valid.',
                ]);
            }

            $newVersion = $lockedLetter->current_revision_no + 1;
            $formVariables = $data['variables'] ?? [];
            $renderedHtml = $this->templateEngine->render($template->content_template, $formVariables);

            $content = [
                'variables' => $formVariables,
                'rendered_body' => $renderedHtml,
                'template_title' => $template->title,
                'template_version' => $template->version,
            ];

            $contentJson = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $contentHash = hash('sha256', $contentJson);

            // Simpan revisi baru secara append-only
            $newRevision = LetterRevision::create([
                'letter_id' => $lockedLetter->id,
                'version' => $newVersion,
                'template_id' => $template->id,
                'subject' => trim($data['subject'] ?? $lockedLetter->subject),
                'recipient_name' => trim($data['recipient_name'] ?? $lockedLetter->recipient_name),
                'classification' => $classification,
                'planned_date' => $data['planned_date'] ?? $lockedLetter->planned_date,
                'content' => $content,
                'content_sha256' => $contentHash,
                'change_summary' => trim($data['change_summary'] ?? "Pembaruan draf ke versi {$newVersion}"),
                'created_by' => $editor->id,
                'created_at' => now(),
            ]);

            // Perbarui header surat ke versi revisi terkini
            $lockedLetter->update([
                'subject' => $newRevision->subject,
                'recipient_name' => $newRevision->recipient_name,
                'classification' => $newRevision->classification,
                'planned_date' => $newRevision->planned_date,
                'template_id' => $newRevision->template_id,
                'current_revision_no' => $newVersion,
                // official_number tetap NULL
            ]);

            AuditEvent::create([
                'organization_id' => $editor->organization_id,
                'actor_id' => $editor->id,
                'event_type' => 'LETTER_REVISED',
                'entity_type' => 'LETTER',
                'entity_id' => $lockedLetter->id,
                'event_metadata' => [
                    'from_version' => $expectedRevisionNo,
                    'to_version' => $newVersion,
                    'content_sha256' => $contentHash,
                    'change_summary' => $newRevision->change_summary,
                ],
            ]);

            return $newRevision;
        });
    }

    /**
     * Mencatat jejak audit pratinjau surat.
     */
    public function recordPreviewAudit(User $viewer, Letter $letter, ?int $revisionVersion = null): void
    {
        AuditEvent::create([
            'organization_id' => $viewer->organization_id,
            'actor_id' => $viewer->id,
            'event_type' => 'LETTER_PREVIEWED',
            'entity_type' => 'LETTER',
            'entity_id' => $letter->id,
            'event_metadata' => [
                'version' => $revisionVersion ?? $letter->current_revision_no,
                'classification' => $letter->classification,
                'is_watermarked' => true,
            ],
        ]);
    }
}
