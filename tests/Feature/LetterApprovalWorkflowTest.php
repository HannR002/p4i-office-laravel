<?php

namespace Tests\Feature;

use App\Exceptions\StaleRevisionException;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\SignatoryMandate;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\LetterDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class LetterApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;

    protected Organization $orgB;

    protected User $authorA;

    protected User $reviewerA;

    protected User $approverA;

    protected User $adminA;

    protected User $outsiderB;

    protected LetterType $typeA;

    protected LetterTemplate $templateA;

    protected SignatoryMandate $reviewMandateA;

    protected SignatoryMandate $approveMandateA;

    protected SignatoryMandate $expiredMandateA;

    protected SignatoryMandate $revokedMandateA;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Organisasi A
        $this->orgA = Organization::create([
            'legal_name' => 'Yayasan P4I Utama',
            'acronym' => 'P4I-A',
        ]);

        $unitA = OrgUnit::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Sekretariat Utama',
        ]);

        $roleAdmin = Role::create(['role_key' => 'SYSTEM_ADMIN', 'label' => 'Admin']);
        $roleStaff = Role::create(['role_key' => 'STAFF', 'label' => 'Staf']);
        $roleReviewer = Role::create(['role_key' => 'REVIEWER', 'label' => 'Reviewer']);
        $rolePengurus = Role::create(['role_key' => 'PENGURUS_YAYASAN', 'label' => 'Pengurus']);

        // Staf Pembuat Draf
        $this->authorA = User::create([
            'organization_id' => $this->orgA->id,
            'unit_id' => $unitA->id,
            'email' => 'author_a@p4i.or.id',
            'display_name' => 'Staf Pembuat Draf',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->authorA->roles()->attach($roleStaff->id);

        // Reviewer (Hanya Berwenang Menelaah / REVIEW)
        $this->reviewerA = User::create([
            'organization_id' => $this->orgA->id,
            'unit_id' => $unitA->id,
            'email' => 'reviewer_a@p4i.or.id',
            'display_name' => 'Penelaah Naskah A',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->reviewerA->roles()->attach($roleReviewer->id);

        // Pengurus (Berwenang Menyetujui / APPROVE)
        $this->approverA = User::create([
            'organization_id' => $this->orgA->id,
            'unit_id' => $unitA->id,
            'email' => 'approver_a@p4i.or.id',
            'display_name' => 'Ketua Pengurus A',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->approverA->roles()->attach([$rolePengurus->id, $roleReviewer->id]);

        // Administrator
        $this->adminA = User::create([
            'organization_id' => $this->orgA->id,
            'unit_id' => $unitA->id,
            'email' => 'admin_a@p4i.or.id',
            'display_name' => 'Admin Sistem A',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->adminA->roles()->attach($roleAdmin->id);

        // 2. Organisasi B (Tenant Lain)
        $this->orgB = Organization::create([
            'legal_name' => 'Yayasan Mitra B',
            'acronym' => 'MITRA-B',
        ]);

        $this->outsiderB = User::create([
            'organization_id' => $this->orgB->id,
            'email' => 'outsider_b@mitra.or.id',
            'display_name' => 'Staf Organisasi B',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->outsiderB->roles()->attach($roleStaff->id);

        // 3. Jenis Surat & Templat di Org A
        $this->typeA = LetterType::create([
            'organization_id' => $this->orgA->id,
            'code' => 'KET-M2',
            'label' => 'Surat Keterangan Sprint M2',
            'is_active' => true,
        ]);

        $this->templateA = LetterTemplate::create([
            'organization_id' => $this->orgA->id,
            'letter_type_id' => $this->typeA->id,
            'version' => 1,
            'title' => 'Templat Standar M2',
            'form_schema' => ['fields' => ['nama']],
            'content_template' => '<p>Menerangkan bahwa {{nama}} telah terdaftar.</p>',
            'is_active' => true,
        ]);

        // 4. Mandat Pejabat (Signatory Mandates)
        // A. Mandat Review Sah
        $this->reviewMandateA = SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->reviewerA->id,
            'letter_type_id' => $this->typeA->id,
            'can_review' => true,
            'can_approve' => false,
            'can_sign' => false,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'status' => 'ACTIVE',
            'decree_number' => '[SIMULASI] SK-01/REV/2026',
            'is_simulation' => true,
        ]);

        // B. Mandat Approve & Review Sah
        $this->approveMandateA = SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->approverA->id,
            'letter_type_id' => null, // Berlaku umum
            'can_review' => true,
            'can_approve' => true,
            'can_sign' => false,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'status' => 'ACTIVE',
            'decree_number' => '[SIMULASI] SK-02/APP/2026',
            'is_simulation' => true,
        ]);

        // C. Mandat Kadaluarsa (Expired)
        $this->expiredMandateA = SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->reviewerA->id,
            'letter_type_id' => $this->typeA->id,
            'can_review' => true,
            'can_approve' => true,
            'can_sign' => false,
            'valid_from' => '2025-01-01',
            'valid_to' => '2025-12-31',
            'status' => 'EXPIRED',
            'decree_number' => '[SIMULASI] SK-EXPIRED/2025',
            'is_simulation' => true,
        ]);

        // D. Mandat Dicabut (Revoked)
        $this->revokedMandateA = SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->reviewerA->id,
            'letter_type_id' => $this->typeA->id,
            'can_review' => true,
            'can_approve' => true,
            'can_sign' => false,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'status' => 'REVOKED',
            'revoked_at' => now(),
            'decree_number' => '[SIMULASI] SK-REVOKED/2026',
            'is_simulation' => true,
        ]);
    }

    /**
     * Helper untuk membuat draf surat awal.
     */
    protected function createTestDraft(array $overrides = []): Letter
    {
        $draftService = app(LetterDraftService::class);

        return $draftService->createDraft($this->authorA, array_merge([
            'subject' => 'Surat Uji Daur Hidup M2',
            'recipient_name' => 'Penerima Uji M2',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->templateA->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-20',
            'variables' => ['nama' => 'Budi Pratama'],
        ], $overrides));
    }

    /**
     * AP2-01: Submit revisi terbaru berhasil dan permintaan bersifat idempotent.
     */
    public function test_ap2_01_submit_latest_revision_and_idempotency(): void
    {
        $letter = $this->createTestDraft();
        $this->assertEquals('DRAFT', $letter->status);

        $workflowService = app(ApprovalWorkflowService::class);
        $idempotencyKey = (string) Str::uuid();

        // 1. Submit pertama
        $req1 = $workflowService->submit($this->authorA, $letter, [
            'submission_notes' => 'Catatan pengajuan pertama',
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->assertEquals('SUBMITTED', $letter->fresh()->status);
        $this->assertEquals('PENDING', $req1->status);
        $this->assertEquals(1, $req1->revision_version);
        $this->assertEquals($idempotencyKey, $req1->idempotency_key);

        // 2. Submit ulang dengan idempotency key yang sama (Replay)
        $req2 = $workflowService->submit($this->authorA, $letter->fresh(), [
            'submission_notes' => 'Catatan pengajuan ulang',
            'idempotency_key' => $idempotencyKey,
        ]);

        // Wajib mengembalikan objek request yang sama persis tanpa duplikasi record
        $this->assertEquals($req1->id, $req2->id);
        $this->assertEquals(1, ApprovalRequest::where('letter_id', $letter->id)->count());
    }

    /**
     * AP2-02: Anonymous, disabled user, serta akses lintas organisasi ditolak.
     */
    public function test_ap2_02_anonymous_disabled_and_cross_organization_denied(): void
    {
        $letter = $this->createTestDraft();

        // 1. Pengguna Anonim (Guest) ditolak
        $responseAnon = $this->post("/letters/{$letter->id}/submit");
        $responseAnon->assertRedirect('/login');

        // 2. Pengguna DISABLED ditolak saat mengakses antrean
        $this->reviewerA->update(['status' => 'DISABLED']);
        $responseDisabled = $this->actingAs($this->reviewerA)->get('/approvals');
        $responseDisabled->assertRedirect('/login');
        $this->reviewerA->update(['status' => 'ACTIVE']);

        // 3. Akses lintas organisasi (Org B mencoba submit atau claim surat Org A)
        $responseCrossSubmit = $this->actingAs($this->outsiderB)->post("/letters/{$letter->id}/submit");
        $responseCrossSubmit->assertStatus(403);

        $responseCrossQueue = $this->actingAs($this->outsiderB)->get("/approvals/{$letter->id}");
        $responseCrossQueue->assertStatus(403);
    }

    /**
     * AP2-03: Reviewer tanpa mandat aktif ditolak saat menelaah atau menyetujui.
     */
    public function test_ap2_03_reviewer_without_active_mandate_denied(): void
    {
        $letter = $this->createTestDraft();
        $workflowService = app(ApprovalWorkflowService::class);
        $workflowService->submit($this->authorA, $letter);

        // User tanpa mandat sama sekali
        $roleReviewer = Role::where('role_key', 'REVIEWER')->first();
        $userTanpaMandat = User::create([
            'organization_id' => $this->orgA->id,
            'email' => 'tanpa_mandat@p4i.or.id',
            'display_name' => 'User Tanpa Mandat',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $userTanpaMandat->roles()->attach($roleReviewer->id);

        $this->expectException(ValidationException::class);
        $workflowService->claimReview($userTanpaMandat, $letter->fresh());
    }

    /**
     * AP2-04: Reviewer sah dapat mengklaim (claim) dan menyetujui (approve) revisi yang tepat.
     */
    public function test_ap2_04_valid_reviewer_can_claim_and_approve_exact_revision(): void
    {
        $letter = $this->createTestDraft();
        $workflowService = app(ApprovalWorkflowService::class);

        // Submit draf
        $workflowService->submit($this->authorA, $letter);

        // Reviewer memulai pemeriksaan (claim)
        $req = $workflowService->claimReview($this->reviewerA, $letter->fresh());
        $this->assertEquals('IN_REVIEW', $letter->fresh()->status);
        $this->assertEquals('IN_REVIEW', $req->fresh()->status);
        $this->assertEquals($this->reviewerA->id, $req->fresh()->claimed_by);

        // Pengurus dengan mandat approve menyetujui draf
        $step = $workflowService->approve($this->approverA, $letter->fresh(), [
            'comments' => 'Naskah draf disetujui tanpa catatan.',
            'expected_version' => 1,
            'expected_sha256' => $letter->latestRevision->content_sha256,
        ]);

        $letterApproved = $letter->fresh();
        $this->assertEquals('APPROVED', $letterApproved->status);
        $this->assertNull($letterApproved->official_number, 'official_number must remain strictly NULL in Sprint M2');
        $this->assertEquals('APPROVED', $step->decision);
        $this->assertEquals($this->approveMandateA->id, $step->mandate_id);
    }

    /**
     * AP2-05: Penyetujuan dengan versi usang atau hash tidak cocok ditolak (409 Conflict).
     */
    public function test_ap2_05_stale_revision_or_hash_mismatch_rejected_with_409(): void
    {
        $letter = $this->createTestDraft();
        $workflowService = app(ApprovalWorkflowService::class);
        $workflowService->submit($this->authorA, $letter);
        $workflowService->claimReview($this->approverA, $letter->fresh());

        // 1. Mismatch Versi (Versi surat v1, tapi pengurus mencoba approve v2)
        try {
            $workflowService->approve($this->approverA, $letter->fresh(), [
                'expected_version' => 99, // Stale version
                'expected_sha256' => $letter->latestRevision->content_sha256,
            ]);
            $this->fail('Mismatched version must throw StaleRevisionException');
        } catch (StaleRevisionException $e) {
            $this->assertStringContainsString('Versi revisi surat telah diperbarui', $e->getMessage());
        }

        // 2. Mismatch Hash Konten SHA-256
        try {
            $workflowService->approve($this->approverA, $letter->fresh(), [
                'expected_version' => 1,
                'expected_sha256' => 'tampered-or-outdated-hash-sha256-invalid',
            ]);
            $this->fail('Mismatched hash must throw StaleRevisionException');
        } catch (StaleRevisionException $e) {
            $this->assertStringContainsString('Hash integritas konten revisi tidak cocok', $e->getMessage());
        }
    }

    /**
     * AP2-06: Self-approval ditolak (Pembuat draf dilarang menyetujui pengajuannya sendiri).
     */
    public function test_ap2_06_self_approval_rejected_even_for_admin(): void
    {
        // Admin membuat draf surat miliknya sendiri
        $draftService = app(LetterDraftService::class);
        $adminLetter = $draftService->createDraft($this->adminA, [
            'subject' => 'Draf Buatan Admin Sendiri',
            'recipient_name' => 'Penerima Uji',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->templateA->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-20',
            'variables' => ['nama' => 'Admin Self'],
        ]);

        $workflowService = app(ApprovalWorkflowService::class);
        $workflowService->submit($this->adminA, $adminLetter);

        // Berikan mandat approve pada admin
        SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->adminA->id,
            'can_review' => true,
            'can_approve' => true,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'status' => 'ACTIVE',
            'decree_number' => '[SIMULASI] SK-ADMIN/2026',
        ]);

        // Mencoba approve pengajuannya sendiri wajib melempar AccessDeniedHttpException
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Self-approval ditolak');

        $workflowService->claimReview($this->adminA, $adminLetter->fresh());
    }

    /**
     * AP2-07: Mandat expired, revoked, atau berbeda organisasi ditolak.
     */
    public function test_ap2_07_expired_revoked_and_foreign_mandates_denied(): void
    {
        $letter = $this->createTestDraft();
        $workflowService = app(ApprovalWorkflowService::class);
        $workflowService->submit($this->authorA, $letter);

        // 1. Pengguna dengan mandat expired
        $userExpired = User::create([
            'organization_id' => $this->orgA->id,
            'email' => 'user_expired@p4i.or.id',
            'display_name' => 'User Mandat Kadaluarsa',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $userExpired->id,
            'can_review' => true,
            'can_approve' => true,
            'valid_from' => '2025-01-01',
            'valid_to' => '2025-06-30', // Expired
            'status' => 'EXPIRED',
            'decree_number' => '[SIMULASI] SK-EXP/2025',
        ]);

        try {
            $workflowService->claimReview($userExpired, $letter->fresh());
            $this->fail('Expired mandate must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('mandate', $e->errors());
        }

        // 2. Pengguna dengan mandat dicabut (revoked)
        $userRevoked = User::create([
            'organization_id' => $this->orgA->id,
            'email' => 'user_revoked@p4i.or.id',
            'display_name' => 'User Mandat Dicabut',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        SignatoryMandate::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $userRevoked->id,
            'can_review' => true,
            'can_approve' => true,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'status' => 'REVOKED',
            'revoked_at' => now(),
            'decree_number' => '[SIMULASI] SK-REV/2026',
        ]);

        try {
            $workflowService->claimReview($userRevoked, $letter->fresh());
            $this->fail('Revoked mandate must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('mandate', $e->errors());
        }
    }

    /**
     * AP2-08: Request changes menghasilkan alasan dan membuka siklus revisi baru.
     */
    public function test_ap2_08_request_changes_requires_reason_and_unlocks_new_revision(): void
    {
        $letter = $this->createTestDraft();
        $workflowService = app(ApprovalWorkflowService::class);
        $draftService = app(LetterDraftService::class);

        $workflowService->submit($this->authorA, $letter);
        $workflowService->claimReview($this->reviewerA, $letter->fresh());

        // 1. Upaya request changes tanpa alasan wajib ditolak
        try {
            $workflowService->requestChanges($this->reviewerA, $letter->fresh(), [
                'reason' => '   ', // Kosong
            ]);
            $this->fail('Request changes without reason must fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        // 2. Request changes dengan alasan yang sah
        $step = $workflowService->requestChanges($this->reviewerA, $letter->fresh(), [
            'reason' => 'Mohon tambahkan nomor referensi kegiatan pada isi surat.',
        ]);

        $this->assertEquals('CHANGES_REQUESTED', $letter->fresh()->status);
        $this->assertEquals('CHANGES_REQUESTED', $step->decision);

        // 3. Staf pembuat draf sekarang dapat merevisi kembali draf tersebut ke revisi v2
        $rev2 = $draftService->reviseDraft($this->authorA, $letter->fresh(), [
            'subject' => 'Surat Uji Daur Hidup M2 (Revisi Perbaikan)',
            'change_summary' => 'Menambahkan nomor referensi kegiatan sesuai arahan reviewer',
            'variables' => ['nama' => 'Budi Pratama S.T.'],
        ], expectedRevisionNo: 1);

        $this->assertEquals(2, $rev2->version);
        // Status surat wajib kembali menjadi DRAFT setelah revisi baru disimpan
        $this->assertEquals('DRAFT', $letter->fresh()->status);
        $this->assertEquals(2, $letter->fresh()->current_revision_no);
    }

    /**
     * AP2-09: Reject wajib memiliki alasan dan mengunci draf dari mutasi tidak sengaja.
     */
    public function test_ap2_09_reject_requires_reason_and_locks_subsequent_mutation(): void
    {
        $letter = $this->createTestDraft();
        $workflowService = app(ApprovalWorkflowService::class);
        $draftService = app(LetterDraftService::class);

        $workflowService->submit($this->authorA, $letter);
        $workflowService->claimReview($this->reviewerA, $letter->fresh());

        // 1. Reject tanpa alasan wajib gagal
        try {
            $workflowService->reject($this->reviewerA, $letter->fresh(), ['reason' => '']);
            $this->fail('Reject without reason must fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        // 2. Reject dengan alasan sah
        $step = $workflowService->reject($this->reviewerA, $letter->fresh(), [
            'reason' => 'Kegiatan tidak sesuai dengan kalender resmi yayasan.',
        ]);

        $this->assertEquals('REJECTED', $letter->fresh()->status);
        $this->assertEquals('REJECTED', $step->decision);

        // 3. Draf yang ditolak tidak dapat diedit lagi
        $this->expectException(\InvalidArgumentException::class);
        $draftService->reviseDraft($this->authorA, $letter->fresh(), [
            'subject' => 'Upaya Revisi Surat Ditolak',
        ], expectedRevisionNo: 1);
    }

    /**
     * AP2-11: Retry tidak menimbulkan submit atau decision ganda.
     */
    public function test_ap2_11_retries_do_not_double_submit_or_double_decide(): void
    {
        $letter = $this->createTestDraft();
        $workflowService = app(ApprovalWorkflowService::class);

        $submitKey = (string) Str::uuid();
        $req1 = $workflowService->submit($this->authorA, $letter, ['idempotency_key' => $submitKey]);
        $req2 = $workflowService->submit($this->authorA, $letter->fresh(), ['idempotency_key' => $submitKey]);
        $this->assertEquals($req1->id, $req2->id);

        $workflowService->claimReview($this->approverA, $letter->fresh());

        $decisionKey = (string) Str::uuid();
        $step1 = $workflowService->approve($this->approverA, $letter->fresh(), [
            'idempotency_key' => $decisionKey,
            'expected_version' => 1,
        ]);

        $step2 = $workflowService->approve($this->approverA, $letter->fresh(), [
            'idempotency_key' => $decisionKey,
            'expected_version' => 1,
        ]);

        $this->assertEquals($step1->id, $step2->id);
        $this->assertEquals(1, ApprovalStep::where('letter_id', $letter->id)->count());
    }

    /**
     * AP2-12: Audit trail dan status history tercatat atomik pada seluruh transisi.
     */
    public function test_ap2_12_audit_trail_and_status_history_atomic(): void
    {
        $letter = $this->createTestDraft();
        $workflowService = app(ApprovalWorkflowService::class);

        // Submit
        $workflowService->submit($this->authorA, $letter);
        $this->assertDatabaseHas('letter_status_history', [
            'letter_id' => $letter->id,
            'from_status' => 'DRAFT',
            'to_status' => 'SUBMITTED',
            'actor_id' => $this->authorA->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'entity_id' => $letter->id,
            'event_type' => 'LETTER_SUBMITTED',
            'actor_id' => $this->authorA->id,
        ]);

        // Review started
        $workflowService->claimReview($this->reviewerA, $letter->fresh());
        $this->assertDatabaseHas('letter_status_history', [
            'letter_id' => $letter->id,
            'from_status' => 'SUBMITTED',
            'to_status' => 'IN_REVIEW',
            'actor_id' => $this->reviewerA->id,
        ]);

        // Approve
        $workflowService->approve($this->approverA, $letter->fresh());
        $this->assertDatabaseHas('letter_status_history', [
            'letter_id' => $letter->id,
            'from_status' => 'IN_REVIEW',
            'to_status' => 'APPROVED',
            'actor_id' => $this->approverA->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'entity_id' => $letter->id,
            'event_type' => 'LETTER_APPROVED',
            'actor_id' => $this->approverA->id,
        ]);
    }

    /**
     * AP2-13: Dokumen rahasia dan antrean reviewer terisolasi.
     */
    public function test_ap2_13_confidential_docs_and_reviewer_queue_isolated(): void
    {
        $draftService = app(LetterDraftService::class);
        $workflowService = app(ApprovalWorkflowService::class);

        // Buat surat rahasia
        $confidentialLetter = $draftService->createDraft($this->authorA, [
            'subject' => 'Dokumen Sangat Rahasia M2',
            'recipient_name' => 'Kementerian Terkait',
            'letter_type_id' => $this->typeA->id,
            'template_id' => $this->templateA->id,
            'classification' => 'RAHASIA',
            'planned_date' => '2026-11-20',
            'variables' => ['nama' => 'Rahasia'],
        ]);

        $workflowService->submit($this->authorA, $confidentialLetter);

        // Staf lain di Org A tidak boleh melihat surat rahasia ini di antrean
        $otherStaff = User::create([
            'organization_id' => $this->orgA->id,
            'email' => 'other_staff@p4i.or.id',
            'display_name' => 'Staf Lain',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $roleStaff = Role::where('role_key', 'STAFF')->first();
        $otherStaff->roles()->attach($roleStaff->id);

        $responseStaff = $this->actingAs($otherStaff)->get('/approvals');
        $responseStaff->assertStatus(403);

        // Reviewer biasa tanpa hak khusus tidak melihat dokumen rahasia di antrean
        $responseReviewer = $this->actingAs($this->reviewerA)->get('/approvals');
        $responseReviewer->assertStatus(200);
        $responseReviewer->assertDontSee('Dokumen Sangat Rahasia M2');

        // Pengurus Yayasan atau Admin dapat melihat dokumen rahasia
        $responseAdmin = $this->actingAs($this->adminA)->get('/approvals');
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('Dokumen Sangat Rahasia M2');
    }

    /**
     * AP2-16: Negative CSRF test menggunakan proteksi yang aktif (HTTP 419).
     */
    public function test_ap2_16_csrf_negative_test_verified(): void
    {
        $letter = $this->createTestDraft();

        $this->app['env'] = 'production';
        try {
            $response = $this->actingAs($this->authorA)
                ->withSession(['_token' => 'server-session-valid-token'])
                ->post("/letters/{$letter->id}/submit", [
                    '_token' => 'tampered-csrf-token-invalid',
                ]);

            $response->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    /**
     * AP2-18: Penomoran resmi OFF, official_number tetap NULL, tanpa tanda tangan final.
     */
    public function test_ap2_18_official_numbering_off_and_null_after_approval(): void
    {
        $letter = $this->createTestDraft();
        $workflowService = app(ApprovalWorkflowService::class);

        $workflowService->submit($this->authorA, $letter);
        $workflowService->claimReview($this->approverA, $letter->fresh());
        $workflowService->approve($this->approverA, $letter->fresh());

        $approvedLetter = $letter->fresh();
        $this->assertEquals('APPROVED', $approvedLetter->status);
        $this->assertNull($approvedLetter->official_number, 'official_number MUST be NULL even after APPROVED');

        // Pratinjau tetap menampilkan watermark dan nomor belum diterbitkan
        $response = $this->actingAs($this->authorA)->get("/letters/{$letter->id}/preview");
        $response->assertStatus(200);
        $response->assertSee('DRAF — BELUM RESMI');
        $response->assertSee('BELUM DITERBITKAN (DRAF)');
    }
}
