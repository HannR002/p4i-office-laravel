<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\AuditEvent;
use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterStatusHistory;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\NumberingPolicy;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\SignatoryMandate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_15_tables_can_be_created_and_related_transactionally(): void
    {
        // 1. Organization
        $org = Organization::create([
            'legal_name' => 'Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia',
            'acronym' => 'Yayasan P4I',
            'address_placeholder' => 'Gedung P4I Lantai 3',
        ]);
        $this->assertNotEmpty($org->id);

        // 2. Org Unit
        $unit = OrgUnit::create([
            'organization_id' => $org->id,
            'name' => 'Sekretariat Utama',
            'active' => true,
        ]);
        $this->assertNotEmpty($unit->id);

        // 3. User
        $user = User::create([
            'organization_id' => $org->id,
            'unit_id' => $unit->id,
            'email' => 'admin@p4i.local',
            'display_name' => 'Administrator Yayasan',
            'password' => 'secret12345',
            'status' => 'ACTIVE',
        ]);
        $this->assertNotEmpty($user->id);

        // 4. Role
        $role = Role::create([
            'role_key' => 'SYSTEM_ADMIN',
            'label' => 'Administrator Sistem',
        ]);
        $this->assertNotEmpty($role->id);

        // 5. User Role
        $user->roles()->attach($role->id, ['granted_by' => $user->id]);
        $this->assertTrue($user->isSystemAdmin());

        // 6. Numbering Policy
        $policy = NumberingPolicy::create([
            'organization_id' => $org->id,
            'series_key' => 'UMUM_2026',
            'code' => 'P4I',
            'format_pattern' => '{seq:03}/{code}/{month_roman}/{year}',
            'reset_period' => 'YEAR',
            'padding' => 3,
            'is_active' => false,
        ]);
        $this->assertNotEmpty($policy->id);

        // 7. Letter Type
        $letterType = LetterType::create([
            'organization_id' => $org->id,
            'code' => 'KET-MAGANG',
            'label' => 'Surat Keterangan Magang',
            'priority' => 'P0',
            'numbering_policy_id' => $policy->id,
            'is_active' => true,
        ]);
        $this->assertNotEmpty($letterType->id);

        // 8. Letter Template
        $template = LetterTemplate::create([
            'organization_id' => $org->id,
            'letter_type_id' => $letterType->id,
            'version' => 1,
            'title' => 'Templat Standar Keterangan Magang',
            'description' => 'Templat resmi untuk sertifikasi magang',
            'form_schema' => ['fields' => ['nama_mahasiswa', 'universitas', 'nilai']],
            'content_template' => '<p>Menerangkan bahwa {{nama}} telah selesai magang.</p>',
            'is_active' => true,
        ]);
        $this->assertNotEmpty($template->id);

        // 9. Letter
        $letter = Letter::create([
            'organization_id' => $org->id,
            'unit_id' => $unit->id,
            'letter_type_id' => $letterType->id,
            'template_id' => $template->id,
            'author_id' => $user->id,
            'subject' => 'Surat Keterangan Magang Sdr. Budi',
            'recipient_name' => 'Universitas Indonesia',
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-01',
            'status' => 'DRAFT',
            'current_revision_no' => 1,
            'official_number' => null,
        ]);
        $this->assertNotEmpty($letter->id);

        // 10. Letter Revision (Immutable)
        $revision = LetterRevision::create([
            'letter_id' => $letter->id,
            'version' => 1,
            'template_id' => $template->id,
            'subject' => $letter->subject,
            'recipient_name' => $letter->recipient_name,
            'classification' => $letter->classification,
            'planned_date' => $letter->planned_date,
            'content' => ['body' => 'Isi draf naskah magang versi 1'],
            'content_sha256' => hash('sha256', json_encode(['body' => 'Isi draf naskah magang versi 1'])),
            'change_summary' => 'Inisiasi draf awal',
            'created_by' => $user->id,
            'created_at' => now(),
        ]);
        $this->assertNotEmpty($revision->id);

        // 11. Signatory Mandate
        $mandate = SignatoryMandate::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'can_review' => true,
            'can_approve' => true,
            'can_sign' => false,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'status' => 'ACTIVE',
            'decree_number' => 'SK-P4I/2026/001-TEST',
            'notes' => 'Mandat pengujian pengurus',
        ]);
        $this->assertNotEmpty($mandate->id);
        $this->assertTrue($mandate->isValidNow());

        // 12. Approval Request
        $appRequest = ApprovalRequest::create([
            'organization_id' => $org->id,
            'letter_id' => $letter->id,
            'revision_id' => $revision->id,
            'revision_version' => 1,
            'content_sha256' => $revision->content_sha256,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'status' => 'PENDING',
            'submission_notes' => 'Mohon ditelaah',
        ]);
        $this->assertNotEmpty($appRequest->id);

        // 13. Approval Step
        $step = ApprovalStep::create([
            'approval_request_id' => $appRequest->id,
            'letter_id' => $letter->id,
            'revision_id' => $revision->id,
            'step_no' => 1,
            'reviewer_id' => $user->id,
            'mandate_id' => $mandate->id,
            'decision' => 'APPROVED',
            'decision_content_sha256' => $revision->content_sha256,
            'comments' => 'Disetujui untuk pengujian',
            'decided_at' => now(),
        ]);
        $this->assertNotEmpty($step->id);

        // 14. Letter Status History
        $history = LetterStatusHistory::create([
            'letter_id' => $letter->id,
            'revision_id' => $revision->id,
            'from_status' => 'DRAFT',
            'to_status' => 'SUBMITTED',
            'actor_id' => $user->id,
            'reason' => 'Pengajuan telaah',
            'metadata' => ['version' => 1],
            'created_at' => now(),
        ]);
        $this->assertNotEmpty($history->id);

        // 15. Audit Event
        $audit = AuditEvent::create([
            'organization_id' => $org->id,
            'actor_id' => $user->id,
            'event_type' => 'LETTER_SUBMITTED',
            'entity_type' => 'letter',
            'entity_id' => $letter->id,
            'event_metadata' => ['action' => 'submit'],
            'occurred_at' => now(),
        ]);
        $this->assertNotEmpty($audit->id);

        // Verifikasi Relasi
        $this->assertEquals($org->id, $letter->organization->id);
        $this->assertEquals($user->id, $letter->author->id);
        $this->assertEquals(1, $letter->revisions->count());
        $this->assertEquals($appRequest->id, $step->approvalRequest->id);
    }

    public function test_letter_revision_is_strictly_immutable(): void
    {
        $org = Organization::create(['legal_name' => 'P4I', 'acronym' => 'P4I']);
        $user = User::create(['organization_id' => $org->id, 'email' => 'u@p.local', 'display_name' => 'U', 'password' => 'x']);
        $type = LetterType::create(['organization_id' => $org->id, 'code' => 'T1', 'label' => 'L1']);
        $letter = Letter::create([
            'organization_id' => $org->id,
            'letter_type_id' => $type->id,
            'author_id' => $user->id,
            'subject' => 'Subj',
            'recipient_name' => 'Rec',
            'planned_date' => '2026-11-01',
        ]);

        $rev = LetterRevision::create([
            'letter_id' => $letter->id,
            'version' => 1,
            'subject' => 'Subj',
            'recipient_name' => 'Rec',
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-01',
            'content' => ['k' => 'v'],
            'content_sha256' => str_repeat('a', 64),
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        // Attempt UPDATE -> must throw RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mutasi UPDATE dilarang pada entitas revisi');
        $rev->update(['subject' => 'Mutasi Ilegal']);
    }

    public function test_audit_event_is_strictly_immutable(): void
    {
        $org = Organization::create(['legal_name' => 'P4I', 'acronym' => 'P4I']);
        $audit = AuditEvent::create([
            'organization_id' => $org->id,
            'event_type' => 'TEST_EVENT',
            'entity_type' => 'test',
            'occurred_at' => now(),
        ]);

        // Attempt DELETE -> must throw RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mutasi DELETE dilarang pada entitas audit');
        $audit->delete();
    }
}

