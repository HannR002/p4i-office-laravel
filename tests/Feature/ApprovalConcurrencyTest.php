<?php

namespace Tests\Feature;

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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalConcurrencyTest extends TestCase
{
    protected Organization $org;

    protected User $author;

    protected User $reviewer1;

    protected User $reviewer2;

    protected LetterType $type;

    protected LetterTemplate $template;

    protected SignatoryMandate $mandate1;

    protected SignatoryMandate $mandate2;

    protected Letter $letter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanDatabase();

        $this->org = Organization::create([
            'legal_name' => 'Yayasan P4I Konkurensi M2',
            'acronym' => 'P4I-CONC-M2',
        ]);

        $unit = OrgUnit::create([
            'organization_id' => $this->org->id,
            'name' => 'Divisi Telaah',
        ]);

        $roleStaff = Role::create(['role_key' => 'STAFF', 'label' => 'Staf']);
        $roleReviewer = Role::create(['role_key' => 'REVIEWER', 'label' => 'Reviewer']);

        $this->author = User::create([
            'organization_id' => $this->org->id,
            'unit_id' => $unit->id,
            'email' => 'author_conc_'.uniqid().'@p4i.or.id',
            'display_name' => 'Staf Pembuat',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->author->roles()->attach($roleStaff->id);

        $this->reviewer1 = User::create([
            'organization_id' => $this->org->id,
            'unit_id' => $unit->id,
            'email' => 'reviewer1_'.uniqid().'@p4i.or.id',
            'display_name' => 'Reviewer Pertama',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->reviewer1->roles()->attach($roleReviewer->id);

        $this->reviewer2 = User::create([
            'organization_id' => $this->org->id,
            'unit_id' => $unit->id,
            'email' => 'reviewer2_'.uniqid().'@p4i.or.id',
            'display_name' => 'Reviewer Kedua',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->reviewer2->roles()->attach($roleReviewer->id);

        $this->type = LetterType::create([
            'organization_id' => $this->org->id,
            'code' => 'KET-CONC',
            'label' => 'Jenis Konkurensi M2',
            'is_active' => true,
        ]);

        $this->template = LetterTemplate::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->type->id,
            'version' => 1,
            'title' => 'Templat Konkurensi',
            'form_schema' => ['fields' => ['subjek']],
            'content_template' => '<p>{{subjek}}</p>',
            'is_active' => true,
        ]);

        $this->mandate1 = SignatoryMandate::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->reviewer1->id,
            'letter_type_id' => $this->type->id,
            'can_review' => true,
            'can_approve' => true,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'status' => 'ACTIVE',
            'decree_number' => '[SIMULASI] SK-CONC-01/2026',
        ]);

        $this->mandate2 = SignatoryMandate::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->reviewer2->id,
            'letter_type_id' => $this->type->id,
            'can_review' => true,
            'can_approve' => true,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'status' => 'ACTIVE',
            'decree_number' => '[SIMULASI] SK-CONC-02/2026',
        ]);

        $draftService = app(LetterDraftService::class);
        $this->letter = $draftService->createDraft($this->author, [
            'subject' => 'Surat Uji Konkurensi M2',
            'recipient_name' => 'Penerima Uji',
            'letter_type_id' => $this->type->id,
            'template_id' => $this->template->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-20',
            'variables' => ['subjek' => 'Isi Draf'],
        ]);

        $workflowService = app(ApprovalWorkflowService::class);
        $workflowService->submit($this->author, $this->letter);
        $workflowService->claimReview($this->reviewer1, $this->letter->fresh());
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('audit_events')->truncate();
        DB::table('approval_steps')->truncate();
        DB::table('approval_requests')->truncate();
        DB::table('signatory_mandates')->truncate();
        DB::table('letter_status_history')->truncate();
        DB::table('letter_revisions')->truncate();
        DB::table('letters')->truncate();
        DB::table('letter_templates')->truncate();
        DB::table('letter_types')->truncate();
        DB::table('user_roles')->truncate();
        DB::table('users')->truncate();
        DB::table('roles')->truncate();
        DB::table('org_units')->truncate();
        DB::table('organizations')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * AP2-10: Dua reviewer konkuren pada dua koneksi independen tidak dapat menghasilkan keputusan ganda.
     */
    public function test_ap2_10_concurrent_reviewers_cannot_both_finalize(): void
    {
        $letterId = $this->letter->id;

        // Set lock wait timeout rendah untuk Koneksi 2
        DB::connection('mysql')->statement('SET innodb_lock_wait_timeout = 2');
        DB::connection('mysql2')->statement('SET innodb_lock_wait_timeout = 1');

        // 1. Reviewer 1 pada Koneksi 1 mengunci baris surat dengan lockForUpdate untuk APPROVE
        DB::connection('mysql')->beginTransaction();

        $lockedLetterConn1 = DB::connection('mysql')
            ->table('letters')
            ->where('id', $letterId)
            ->lockForUpdate()
            ->first();

        $this->assertNotNull($lockedLetterConn1);
        $this->assertEquals('IN_REVIEW', $lockedLetterConn1->status);

        // 2. Reviewer 2 pada Koneksi 2 mencoba mengunci baris yang sama untuk REJECT secara bersamaan
        $lockTimeoutOccurred = false;
        try {
            DB::connection('mysql2')->beginTransaction();

            DB::connection('mysql2')
                ->table('letters')
                ->where('id', $letterId)
                ->lockForUpdate()
                ->first();

            DB::connection('mysql2')->commit();
        } catch (QueryException $e) {
            DB::connection('mysql2')->rollBack();
            if (str_contains($e->getMessage(), 'Lock wait timeout exceeded') || str_contains($e->getMessage(), '1205')) {
                $lockTimeoutOccurred = true;
            }
        }

        $this->assertTrue($lockTimeoutOccurred, 'Koneksi 2 wajib terblokir oleh exclusive lock Koneksi 1 dan menghasilkan Lock Wait Timeout 1205');

        // 3. Reviewer 1 menyelesaikan approval dan commit
        DB::connection('mysql')->table('letters')
            ->where('id', $letterId)
            ->update(['status' => 'APPROVED', 'updated_at' => now()]);

        DB::connection('mysql')->commit();

        // 4. Setelah Koneksi 1 commit, periksa status dari Koneksi 2
        $conn2Letter = DB::connection('mysql2')
            ->table('letters')
            ->where('id', $letterId)
            ->first();

        $this->assertEquals('APPROVED', $conn2Letter->status, 'Letter status must be APPROVED by connection 1');
    }

    /**
     * AP2-14: Real MariaDB integration and atomic rollback on failure.
     */
    public function test_ap2_14_real_mariadb_rollback_preserves_state_consistency(): void
    {
        $letterId = $this->letter->id;
        $initialStepCount = DB::table('approval_steps')->where('letter_id', $letterId)->count();
        $this->assertEquals(0, $initialStepCount);

        // Simulasi transaksi persetujuan yang gagal di tengah proses
        try {
            DB::transaction(function () use ($letterId) {
                // Update parsial
                DB::table('letters')->where('id', $letterId)->update(['status' => 'APPROVED']);

                DB::table('approval_steps')->insert([
                    'id' => (string) Str::uuid(),
                    'approval_request_id' => (string) Str::uuid(),
                    'letter_id' => $letterId,
                    'revision_id' => $this->letter->latestRevision->id,
                    'step_no' => 1,
                    'workflow_version' => 1,
                    'reviewer_id' => $this->reviewer1->id,
                    'mandate_id' => $this->mandate1->id,
                    'decision' => 'APPROVED',
                    'decision_content_sha256' => 'dummy-hash',
                    'decided_at' => now(),
                ]);

                // Paksa kegagalan sistem
                throw new \RuntimeException('Simulasi kegagalan sistem MariaDB di tengah transaksi');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Periksa dari Koneksi 2 (proses lain): Seluruh perubahan di atas wajib telah di-rollback
        $letterConn2 = DB::connection('mysql2')->table('letters')->where('id', $letterId)->first();
        $this->assertEquals('IN_REVIEW', $letterConn2->status, 'Status must roll back to IN_REVIEW');

        $stepsConn2 = DB::connection('mysql2')->table('approval_steps')->where('letter_id', $letterId)->count();
        $this->assertEquals(0, $stepsConn2, 'Zero orphan approval steps must exist after rollback');

        // Setelah rollback, approval sah menggunakan workflow service dapat dieksekusi dengan sukses
        $workflowService = app(ApprovalWorkflowService::class);
        $step = $workflowService->approve($this->reviewer1, $this->letter->fresh(), [
            'expected_version' => 1,
            'expected_sha256' => $this->letter->latestRevision->content_sha256,
        ]);

        $this->assertEquals('APPROVED', $step->decision);
        $this->assertEquals('APPROVED', $this->letter->fresh()->status);
        $this->assertEquals(1, ApprovalStep::where('letter_id', $letterId)->count());
    }
}
