<?php

namespace Tests\Feature;

use App\Exceptions\OfficialNumberingBlockedException;
use App\Models\DocumentExport;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\SignatoryMandate;
use App\Models\User;
use App\Services\DocumentExportService;
use App\Services\LetterNumberingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StagingUatPreparationTest extends TestCase
{
    private Organization $org;

    private OrgUnit $unitRiset;

    private OrgUnit $unitSdm;

    private User $staffUser;

    private User $reviewerWithMandate;

    private User $unauthorizedReviewer;

    private User $adminUser;

    private LetterType $letterType;

    private LetterTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia',
            'acronym' => 'Yayasan P4I',
            'address_placeholder' => 'Gedung P4I Lantai 3, Jl. Pembangunan Nasional, Jakarta',
        ]);

        $this->unitRiset = OrgUnit::create([
            'organization_id' => $this->org->id,
            'name' => 'Divisi Riset dan Inovasi',
        ]);

        $this->unitSdm = OrgUnit::create([
            'organization_id' => $this->org->id,
            'name' => 'Divisi SDM dan Umum',
        ]);

        $roleStaff = Role::firstOrCreate(['role_key' => 'STAFF'], ['label' => 'Staf Konseptor']);
        $roleReviewer = Role::firstOrCreate(['role_key' => 'REVIEWER'], ['label' => 'Pejabat Pemeriksa']);
        $roleAdmin = Role::firstOrCreate(['role_key' => 'SYSTEM_ADMIN'], ['label' => 'Administrator Sistem']);

        $this->staffUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'unit_id' => $this->unitRiset->id,
            'email' => 'staf.uat@p4i.or.id',
            'display_name' => 'Rina Kartika (Staf UAT)',
            'password' => bcrypt('UatPassword123!'),
            'status' => 'ACTIVE',
        ]);
        $this->staffUser->roles()->attach($roleStaff->id);

        $this->reviewerWithMandate = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'unit_id' => $this->unitRiset->id,
            'email' => 'reviewer.uat@p4i.or.id',
            'display_name' => 'Dr. Bambang Sutrisno (Pemeriksa Bermandat)',
            'password' => bcrypt('UatPassword123!'),
            'status' => 'ACTIVE',
        ]);
        $this->reviewerWithMandate->roles()->attach($roleReviewer->id);

        SignatoryMandate::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->reviewerWithMandate->id,
            'unit_id' => $this->unitRiset->id,
            'decree_number' => 'SK-SIMULASI-UAT/P4I/2026/01',
            'status' => 'ACTIVE',
            'valid_from' => Carbon::now()->subDays(10)->toDateString(),
            'valid_to' => Carbon::now()->addDays(90)->toDateString(),
            'can_review' => true,
            'can_approve' => true,
        ]);

        $this->unauthorizedReviewer = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'unit_id' => $this->unitSdm->id,
            'email' => 'unauth.uat@p4i.or.id',
            'display_name' => 'Dedi Kurniawan (Reviewer Tanpa Mandat)',
            'password' => bcrypt('UatPassword123!'),
            'status' => 'ACTIVE',
        ]);
        $this->unauthorizedReviewer->roles()->attach($roleReviewer->id);

        $this->adminUser = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'unit_id' => $this->unitRiset->id,
            'email' => 'admin.uat@p4i.or.id',
            'display_name' => 'Agus Setiawan (Admin UAT)',
            'password' => bcrypt('UatPassword123!'),
            'status' => 'ACTIVE',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->letterType = LetterType::create([
            'organization_id' => $this->org->id,
            'code' => 'KET-UAT',
            'label' => 'Surat Keterangan Riset UAT',
            'is_active' => true,
        ]);

        $this->template = LetterTemplate::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'version' => 1,
            'title' => 'Templat Surat Keterangan UAT',
            'form_schema' => ['fields' => ['nama_kegiatan', 'lokasi']],
            'content_template' => '<p>Menerangkan pelaksanaan {{nama_kegiatan}} di {{lokasi}}.</p>',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        $tables = [
            'document_exports',
            'incoming_letters',
            'approval_steps',
            'approval_requests',
            'signatory_mandates',
            'audit_events',
            'letter_revisions',
            'letters',
            'letter_templates',
            'letter_types',
            'user_roles',
            'users',
            'roles',
            'org_units',
            'organizations',
        ];
        foreach ($tables as $t) {
            DB::table($t)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * STG-14 & STG-15: Eksekusi 15 Skenario UAT End-to-End dengan Data Sintetis.
     */
    public function test_stg_14_complete_15_scenario_uat_workflow_executes_end_to_end(): void
    {
        // UAT-01: Staf login
        $loginRes = $this->post('/login', [
            'email' => 'staf.uat@p4i.or.id',
            'password' => 'UatPassword123!',
        ]);
        $loginRes->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->staffUser);

        // UAT-02 & UAT-03: Membuat surat baru dari templat & menyimpan draf (Versi 1)
        $storeRes = $this->actingAs($this->staffUser)->post(route('letters.store'), [
            'subject' => 'Permohonan Izin Survei Lapangan Desa Binaan UAT',
            'recipient_name' => 'Kepala Badan Kesbangpol Kabupaten Bogor',
            'letter_type_id' => $this->letterType->id,
            'template_id' => $this->template->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-15',
            'variables' => [
                'nama_kegiatan' => 'Survei Literasi Digital Desa',
                'lokasi' => 'Kabupaten Bogor',
            ],
        ]);
        $letter = Letter::where('subject', 'Permohonan Izin Survei Lapangan Desa Binaan UAT')->firstOrFail();
        $storeRes->assertRedirect(route('letters.show', $letter));
        $this->assertEquals('DRAFT', $letter->status);
        $this->assertEquals(1, $letter->current_revision_no);
        $this->assertNull($letter->official_number);

        // UAT-04: Merevisi surat (Versi 2)
        $updateRes = $this->actingAs($this->staffUser)->put(route('letters.update', $letter), [
            'subject' => 'Permohonan Izin Survei Lapangan Desa Binaan UAT (Revisi jadwal)',
            'recipient_name' => 'Kepala Badan Kesbangpol Kabupaten Bogor',
            'template_id' => $this->template->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-18',
            'expected_revision_no' => 1,
            'change_summary' => 'Penyesuaian tanggal pelaksanaan survei',
            'variables' => [
                'nama_kegiatan' => 'Survei Literasi Digital Desa Tahap I',
                'lokasi' => 'Kecamatan Cibinong, Kabupaten Bogor',
            ],
        ]);
        $updateRes->assertRedirect(route('letters.show', $letter));
        $letter->refresh();
        $this->assertEquals(2, $letter->current_revision_no);

        // UAT-05: Mengajukan pemeriksaan (DRAFT -> SUBMITTED)
        $submitRes = $this->actingAs($this->staffUser)->post(route('letters.submit', $letter));
        $submitRes->assertRedirect(route('letters.show', $letter));
        $letter->refresh();
        $this->assertEquals('SUBMITTED', $letter->status);

        // UAT-06: Reviewer bermandat mengklaim dan melakukan review (SUBMITTED -> IN_REVIEW)
        $claimRes = $this->actingAs($this->reviewerWithMandate)->post(route('approvals.claim', $letter));
        $claimRes->assertRedirect(route('approvals.show', $letter));
        $letter->refresh();
        $this->assertEquals('IN_REVIEW', $letter->status);

        // UAT-07: Reviewer meminta revisi (IN_REVIEW -> CHANGES_REQUESTED)
        $rev2 = $letter->latestRevision;
        $reqChangesRes = $this->actingAs($this->reviewerWithMandate)->post(route('approvals.decision', $letter), [
            'decision' => 'CHANGES_REQUESTED',
            'reason' => 'Mohon lengkapi jumlah peneliti lapangan pada rincian kegiatan.',
            'expected_version' => $rev2->version,
            'expected_sha256' => $rev2->content_sha256,
        ]);
        $reqChangesRes->assertRedirect(route('approvals.index'));
        $letter->refresh();
        $this->assertEquals('CHANGES_REQUESTED', $letter->status);

        // UAT-08: Staf memperbaiki surat (Versi 3) & mengajukan ulang
        $this->actingAs($this->staffUser)->put(route('letters.update', $letter), [
            'subject' => 'Permohonan Izin Survei Lapangan Desa Binaan UAT (Final Draf)',
            'recipient_name' => 'Kepala Badan Kesbangpol Kabupaten Bogor',
            'template_id' => $this->template->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-18',
            'expected_revision_no' => 2,
            'change_summary' => 'Menambahkan jumlah 5 peneliti lapangan sesuai catatan reviewer',
            'variables' => [
                'nama_kegiatan' => 'Survei Literasi Digital Desa oleh 5 Peneliti P4I',
                'lokasi' => 'Kecamatan Cibinong, Kabupaten Bogor',
            ],
        ]);
        $this->actingAs($this->staffUser)->post(route('letters.submit', $letter));
        $letter->refresh();
        $this->assertEquals('SUBMITTED', $letter->status);
        $this->assertEquals(3, $letter->current_revision_no);

        // UAT-09: Reviewer menyetujui isi draf (IN_REVIEW -> APPROVED, nomor resmi tetap NULL)
        $this->actingAs($this->reviewerWithMandate)->post(route('approvals.claim', $letter));
        $rev3 = $letter->fresh()->latestRevision;
        $approveRes = $this->actingAs($this->reviewerWithMandate)->post(route('approvals.decision', $letter), [
            'decision' => 'APPROVED',
            'comments' => 'Substansi draf telah sesuai dan disetujui sebagai draf internal.',
            'expected_version' => $rev3->version,
            'expected_sha256' => $rev3->content_sha256,
        ]);
        $approveRes->assertRedirect(route('approvals.index'));
        $letter->refresh();
        $this->assertEquals('APPROVED', $letter->status);
        $this->assertNull($letter->official_number, 'Nomor surat resmi wajib tetap NULL meskipun draf telah APPROVED.');

        // UAT-10: Staf mengekspor dan mengunduh PDF & DOCX berlabel DRAF
        $this->actingAs($this->staffUser)->post(route('letters.export', $letter), ['format' => 'PDF']);
        $this->actingAs($this->staffUser)->post(route('letters.export', $letter), ['format' => 'DOCX']);

        $pdfExport = DocumentExport::where('letter_id', $letter->id)->where('format', 'PDF')->firstOrFail();
        $docxExport = DocumentExport::where('letter_id', $letter->id)->where('format', 'DOCX')->firstOrFail();

        $dlPdf = $this->actingAs($this->staffUser)->get(route('archives.download', $pdfExport));
        $dlPdf->assertStatus(200);
        $this->assertStringContainsString('DRAF_', $dlPdf->headers->get('content-disposition'));

        $dlDocx = $this->actingAs($this->staffUser)->get(route('archives.download', $docxExport));
        $dlDocx->assertStatus(200);
        $this->assertStringContainsString('DRAF_', $dlDocx->headers->get('content-disposition'));

        // UAT-11: Pencarian arsip dan verifikasi Buku Agenda Ganda (Surat Keluar & Surat Masuk)
        $searchRes = $this->actingAs($this->staffUser)->get(route('archives.index', ['search' => 'Kesbangpol']));
        $searchRes->assertStatus(200);
        $searchRes->assertSee('Permohonan Izin Survei Lapangan Desa Binaan UAT');

        $outgoingAgendaRes = $this->actingAs($this->staffUser)->get(route('agenda.outgoing'));
        $outgoingAgendaRes->assertStatus(200);
        $outgoingAgendaRes->assertSee('Permohonan Izin Survei Lapangan Desa Binaan UAT');

        $storeIncomingRes = $this->actingAs($this->staffUser)->post(route('agenda.incoming.store'), [
            'sender_name' => 'Badan Riset dan Inovasi Nasional (BRIN)',
            'sender_letter_number' => 'B-402/BRIN/XI/2026',
            'letter_date' => '2026-11-10',
            'received_date' => '2026-11-12',
            'subject' => 'Undangan Koordinasi Riset Pembangunan Daerah UAT',
            'classification' => 'INTERNAL',
            'recipient_unit_id' => $this->unitRiset->id,
            'notes' => 'Disposisi ke Divisi Riset untuk tindak lanjut UAT.',
        ]);
        $storeIncomingRes->assertRedirect(route('agenda.incoming'));

        $incomingAgendaRes = $this->actingAs($this->staffUser)->get(route('agenda.incoming'));
        $incomingAgendaRes->assertStatus(200);
        $incomingAgendaRes->assertSee('Undangan Koordinasi Riset Pembangunan Daerah UAT');

        // UAT-12: Pengguna tidak berwenang ditolak mengakses surat RAHASIA
        $this->actingAs($this->staffUser)->post(route('letters.store'), [
            'subject' => 'Laporan Audit Investigasi Khusus UAT Rahasia',
            'recipient_name' => 'Ketua Yayasan P4I',
            'letter_type_id' => $this->letterType->id,
            'template_id' => $this->template->id,
            'classification' => 'RAHASIA',
            'planned_date' => '2026-11-19',
            'variables' => ['nama_kegiatan' => 'Audit Khusus', 'lokasi' => 'Jakarta'],
        ]);
        $secretLetter = Letter::where('subject', 'Laporan Audit Investigasi Khusus UAT Rahasia')->firstOrFail();
        $secretExport = app(DocumentExportService::class)->exportLetter($this->staffUser, $secretLetter, 'PDF');

        $this->actingAs($this->unauthorizedReviewer)->get(route('letters.show', $secretLetter))->assertStatus(403);
        $this->actingAs($this->unauthorizedReviewer)->get(route('archives.download', $secretExport))->assertStatus(403);

        // UAT-13: Administrator memeriksa riwayat audit
        $adminAuditRes = $this->actingAs($this->adminUser)->get(route('archives.admin'));
        $adminAuditRes->assertStatus(200);
        $adminAuditRes->assertSee('DOCUMENT_EXPORTED');
        $adminAuditRes->assertSee('DOCUMENT_DOWNLOADED');

        // Verifikasi logout sesi pengguna (UAT-01 Login & Logout)
        $logoutRes = $this->actingAs($this->staffUser)->post(route('logout'));
        $logoutRes->assertRedirect('/login');
        $this->assertGuest();

        // UAT-14: Pengguna menguji pemulihan arsip pada lingkungan pengujian
        $disk = Storage::disk('private_archives');
        $backupBytes = $disk->get($pdfExport->file_path);
        $disk->delete($pdfExport->file_path);

        $brokenIntegrity = app(DocumentExportService::class)->verifyArchiveIntegrity($this->adminUser);
        $this->assertFalse($brokenIntegrity['all_intact']);
        $this->assertEquals(1, $brokenIntegrity['missing_files']);

        // Pulihkan berkas dari backup
        $disk->put($pdfExport->file_path, $backupBytes);
        $restoredIntegrity = app(DocumentExportService::class)->verifyArchiveIntegrity($this->adminUser);
        $this->assertTrue($restoredIntegrity['all_intact']);
        $this->assertEquals(0, $restoredIntegrity['missing_files']);

        // UAT-15: Sistem menolak penerbitan nomor surat resmi
        $this->assertFalse(config('features.official_numbering_active', false));
        $this->expectException(OfficialNumberingBlockedException::class);
        app(LetterNumberingService::class)->allocateOfficialNumber();
    }

    /**
     * STG-08, STG-09, STG-10, STG-15, STB-01..STB-07: Pemeriksaan preflight kesiapan teknis lingkungan (Tahap P0).
     */
    public function test_stg_preflight_command_verifies_database_triggers_storage_and_safety_guards(): void
    {
        $exitCode = Artisan::call('p4i:staging-preflight', ['--json' => true]);
        $output = json_decode(Artisan::output(), true);

        $this->assertEquals(0, $exitCode);
        $this->assertTrue($output['php_compatible']);
        $this->assertTrue($output['all_extensions_loaded']);
        $this->assertTrue($output['composer_lock_present']);
        $this->assertTrue($output['innodb_supported']);
        $this->assertTrue($output['all_triggers_present']);
        $this->assertTrue($output['private_storage_outside_public']);
        $this->assertTrue($output['scheduler_registered']);
        $this->assertFalse($output['official_numbering_active']);
        $this->assertEquals(0, $output['non_null_official_numbers']);
        $this->assertTrue($output['safety_invariants_intact']);
        $this->assertEquals('PENDING', $output['hostinger_authorization_gate']);
        $this->assertEquals('LOCAL_PREPARATION_COMPLETE_STAGING_AUTHORIZATION_PENDING', $output['staging_authorization_status']);
        $this->assertEquals('LOCAL_PREPARATION_COMPLETE_HOSTINGER_STAGING_BLOCKED', $output['staging_deployment_status']);
    }
}
