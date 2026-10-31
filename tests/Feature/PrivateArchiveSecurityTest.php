<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentExportService;
use App\Services\ReadinessService;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class PrivateArchiveSecurityTest extends TestCase
{
    private Organization $orgA;

    private Organization $orgB;

    private User $authorA;

    private User $otherStaffA;

    private User $adminA;

    private User $outsiderB;

    private User $disabledUserA;

    private LetterType $letterTypeA;

    private LetterTemplate $templateA;

    private DocumentExportService $exportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $this->exportService = app(DocumentExportService::class);

        // Organisasi A
        $this->orgA = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan P4I Kantor Pusat',
            'acronym' => 'P4I-PUSAT',
        ]);

        $unitA = OrgUnit::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Divisi Kearsipan',
        ]);

        $roleAdmin = Role::firstOrCreate(['role_key' => 'SYSTEM_ADMIN'], ['label' => 'Admin']);
        $roleStaff = Role::firstOrCreate(['role_key' => 'STAFF'], ['label' => 'Staf']);
        $roleReviewer = Role::firstOrCreate(['role_key' => 'REVIEWER'], ['label' => 'Reviewer']);

        $this->authorA = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'unit_id' => $unitA->id,
            'email' => 'author_sec@p4i.or.id',
            'display_name' => 'Konseptor Arsip A',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->authorA->roles()->attach($roleStaff->id);

        $this->otherStaffA = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'unit_id' => $unitA->id,
            'email' => 'other_sec@p4i.or.id',
            'display_name' => 'Staf Lain A',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->otherStaffA->roles()->attach($roleStaff->id);

        $this->adminA = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'unit_id' => $unitA->id,
            'email' => 'admin_sec@p4i.or.id',
            'display_name' => 'Admin Arsip A',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->adminA->roles()->attach($roleAdmin->id);

        $this->disabledUserA = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'unit_id' => $unitA->id,
            'email' => 'disabled_sec@p4i.or.id',
            'display_name' => 'Pengguna Nonaktif',
            'password' => bcrypt('password123'),
            'status' => 'DISABLED',
        ]);
        $this->disabledUserA->roles()->attach($roleStaff->id);

        // Organisasi B (Tenant Asing)
        $this->orgB = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan Mitra Cabang B',
            'acronym' => 'MITRA-B',
        ]);

        $this->outsiderB = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgB->id,
            'email' => 'outsider@mitra-b.or.id',
            'display_name' => 'Pengguna Asing B',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->outsiderB->roles()->attach($roleStaff->id);

        $this->letterTypeA = LetterType::create([
            'organization_id' => $this->orgA->id,
            'code' => 'NOT-SEC',
            'label' => 'Nota Dinas Keamanan',
            'is_active' => true,
        ]);

        $this->templateA = LetterTemplate::create([
            'organization_id' => $this->orgA->id,
            'letter_type_id' => $this->letterTypeA->id,
            'version' => 1,
            'title' => 'Templat Nota Keamanan',
            'form_schema' => ['fields' => ['topik']],
            'content_template' => '<p>Nota dinas perihal {{topik}}.</p>',
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
            'audit_events',
            'letter_revisions',
            'letters',
            'letter_templates',
            'letter_types',
            'org_units',
            'users',
            'organizations',
        ];
        foreach ($tables as $t) {
            DB::table($t)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function createLetter(string $classification = 'INTERNAL'): array
    {
        $letter = Letter::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'letter_type_id' => $this->letterTypeA->id,
            'template_id' => $this->templateA->id,
            'author_id' => $this->authorA->id,
            'classification' => $classification,
            'status' => 'DRAFT',
            'subject' => 'Surat Pengujian Keamanan Arsip',
            'recipient_name' => 'Kepala Biro Umum',
            'planned_date' => now()->toDateString(),
            'current_revision_no' => 1,
            'official_number' => null,
        ]);

        $revision = LetterRevision::create([
            'id' => (string) Str::uuid(),
            'letter_id' => $letter->id,
            'version' => 1,
            'template_id' => $this->templateA->id,
            'subject' => $letter->subject,
            'recipient_name' => $letter->recipient_name,
            'planned_date' => $letter->planned_date,
            'created_by' => $this->authorA->id,
            'classification' => $classification,
            'content' => [
                'body' => ['Uji coba keamanan arsip naskah dinas digital Yayasan P4I.'],
            ],
            'change_summary' => 'Inisiasi uji keamanan',
            'content_sha256' => hash('sha256', 'sec test'),
        ]);

        return [$letter, $revision];
    }

    /**
     * M4A-07: Penyimpanan privat berada di luar public root.
     */
    public function test_m4a_07_private_storage_disk_isolation(): void
    {
        [$letter, $revision] = $this->createLetter();
        $export = $this->exportService->exportLetter($this->authorA, $letter, 'PDF');

        // Pastikan path penyimpanan fisik berada di app/private_archives
        $this->assertStringContainsString('storage/app/private_archives', str_replace('\\', '/', Storage::disk('private_archives')->path('')));

        // Pastikan TIDAK berada di dalam direktori public
        $diskPath = realpath(Storage::disk('private_archives')->path(''));
        $publicPath = realpath(public_path());
        $this->assertFalse(str_starts_with($diskPath, $publicPath), 'Penyimpanan arsip tidak boleh berada di dalam public web root.');
    }

    /**
     * M4A-08: Download authorization (Tamu, Nonaktif, dan Lintas-Tenant ditolak).
     */
    public function test_m4a_08_download_authorization_and_cross_tenant_denied(): void
    {
        [$letter, $revision] = $this->createLetter();
        $export = $this->exportService->exportLetter($this->authorA, $letter, 'PDF');

        // 1. Pengguna Anonim (Guest) dialihkan ke login
        $resGuest = $this->get(route('archives.download', $export));
        $resGuest->assertRedirect(route('login'));

        // 2. Pengguna Nonaktif (Disabled User) ditolak
        $resDisabled = $this->actingAs($this->disabledUserA)->get(route('archives.download', $export));
        $this->assertTrue(in_array($resDisabled->status(), [302, 403]));

        // 3. Pengguna Tenant Asing (Org B) ditolak 403
        $resOutsider = $this->actingAs($this->outsiderB)->get(route('archives.download', $export));
        $resOutsider->assertStatus(403);
    }

    /**
     * M4A-09: Isolasi dokumen berklasifikasi RAHASIA / CONFIDENTIAL.
     */
    public function test_m4a_09_confidential_document_isolation(): void
    {
        [$letter, $revision] = $this->createLetter('RAHASIA');
        $export = $this->exportService->exportLetter($this->authorA, $letter, 'PDF');

        // Staf lain dari organisasi yang sama (bukan author, bukan admin) ditolak 403
        $resOther = $this->actingAs($this->otherStaffA)->get(route('archives.download', $export));
        $resOther->assertStatus(403);

        // Author pemilik dokumen diizinkan mengunduh
        $resAuthor = $this->actingAs($this->authorA)->get(route('archives.download', $export));
        $resAuthor->assertStatus(200);

        // Admin diizinkan mengunduh
        $resAdmin = $this->actingAs($this->adminA)->get(route('archives.download', $export));
        $resAdmin->assertStatus(200);
    }

    /**
     * M4A-10: Anti path-traversal pada parameter download.
     */
    public function test_m4a_10_anti_path_traversal_on_download(): void
    {
        // Percobaan path traversal menggunakan karakter manipulatif
        $resTraversal = $this->actingAs($this->adminA)->get('/archives/..%2F..%2F..%2Fetc%2Fpasswd/download');
        $resTraversal->assertStatus(404);
    }

    /**
     * M4A-11: Verifikasi integritas checksum SHA-256 dan deteksi berkas rusak.
     */
    public function test_m4a_11_sha256_checksum_verification_and_corruption_detection(): void
    {
        [$letter, $revision] = $this->createLetter();
        $export = $this->exportService->exportLetter($this->authorA, $letter, 'PDF');

        // Unduhan awal: Berkas utuh, status 200
        $resInitial = $this->actingAs($this->authorA)->get(route('archives.download', $export));
        $resInitial->assertStatus(200);

        // Simulasi Korupsi Data Fisik di Disk
        $disk = Storage::disk('private_archives');
        $disk->put($export->file_path, 'KONTEN TELAH DIMANIPULASI SECARA LIAR');

        // Unduhan setelah manipulasi: Integritas gagal diverifikasi, status 422
        $resCorrupt = $this->actingAs($this->authorA)->get(route('archives.download', $export));
        $resCorrupt->assertStatus(422);

        // Pengujian Service Audit Integritas
        $auditResult = $this->exportService->verifyArchiveIntegrity($this->authorA);
        $this->assertEquals(1, $auditResult['corrupted_files']);
        $this->assertFalse($auditResult['all_intact']);
    }

    /**
     * M4A-14: UI & Pencarian arsip digital dengan isolasi tenant.
     */
    public function test_m4a_14_search_filtering_and_tenant_scoping(): void
    {
        [$letter, $revision] = $this->createLetter();
        $export = $this->exportService->exportLetter($this->authorA, $letter, 'PDF');

        // 1. Author Org A dapat melihat arsip di daftar
        $resIndexA = $this->actingAs($this->authorA)->get(route('archives.index', ['search' => 'Keamanan']));
        $resIndexA->assertStatus(200);
        $resIndexA->assertSee('Surat Pengujian Keamanan Arsip');

        // 2. Filter Format PDF
        $resFilterPdf = $this->actingAs($this->authorA)->get(route('archives.index', ['format' => 'PDF']));
        $resFilterPdf->assertStatus(200);

        // 3. Pengguna Org B tidak melihat berkas milik Org A
        $resIndexB = $this->actingAs($this->outsiderB)->get(route('archives.index'));
        $resIndexB->assertStatus(200);
        $resIndexB->assertDontSee('Surat Pengujian Keamanan Arsip');
    }

    /**
     * M4A-15: Pencatatan log audit trail ekspor dan unduhan.
     */
    public function test_m4a_15_audit_trail_logging(): void
    {
        [$letter, $revision] = $this->createLetter();

        // 1. Periksa Log Ekspor
        $export = $this->exportService->exportLetter($this->authorA, $letter, 'PDF');
        $exportLog = AuditEvent::where('event_type', 'DOCUMENT_EXPORTED')
            ->where('entity_id', $export->id)
            ->first();

        $this->assertNotNull($exportLog);
        $this->assertEquals($this->authorA->id, $exportLog->actor_id);
        $this->assertEquals($export->file_hash, $exportLog->event_metadata['sha256']);

        // 2. Periksa Log Unduh
        $this->actingAs($this->authorA)->get(route('archives.download', $export));
        $downloadLog = AuditEvent::where('event_type', 'DOCUMENT_DOWNLOADED')
            ->where('entity_id', $export->id)
            ->first();

        $this->assertNotNull($downloadLog);
        $this->assertEquals($this->authorA->id, $downloadLog->actor_id);
    }

    /**
     * M4A-16: Pengujian cadangan (backup) dan pemulihan (restore) arsip digital.
     */
    public function test_m4a_16_archive_backup_and_restore_simulation(): void
    {
        [$letter, $revision] = $this->createLetter();
        $export = $this->exportService->exportLetter($this->authorA, $letter, 'DOCX');

        $disk = Storage::disk('private_archives');
        $originalHash = $export->file_hash;
        $originalContent = $disk->get($export->file_path);

        // Simulasi backup: simpan konten ke direktori cadangan simulasi
        $backupPath = 'backup_test_'.Str::random(8).'.docx';
        Storage::disk('local')->put($backupPath, $originalContent);

        // Simulasi kehilangan berkas asli
        $disk->delete($export->file_path);
        $this->assertFalse($disk->exists($export->file_path));

        // Simulasi restore: kembalikan berkas dari backup
        $restoredContent = Storage::disk('local')->get($backupPath);
        $disk->put($export->file_path, $restoredContent);
        Storage::disk('local')->delete($backupPath);

        // Verifikasi integritas pasca-pemulihan
        $restoredHash = hash_file('sha256', $disk->path($export->file_path));
        $this->assertEquals($originalHash, $restoredHash);

        $integrity = $this->exportService->verifyArchiveIntegrity($this->authorA);
        $this->assertTrue($integrity['all_intact']);
        $this->assertEquals(1, $integrity['valid_files']);
    }

    /**
     * M4A-17: Pure PHP rendering dan kompatibilitas Hostinger shared hosting.
     */
    public function test_m4a_17_hostinger_pure_php_compatibility(): void
    {
        // Verifikasi bahwa class Dompdf dan PhpWord dapat diinisialisasi tanpa dependensi daemon sistem
        $this->assertTrue(class_exists(Dompdf::class));
        $this->assertTrue(class_exists(PhpWord::class));

        // Verifikasi fungsi ZIP dan GD tersedia secara native
        $this->assertTrue(extension_loaded('zip'));
        $this->assertTrue(extension_loaded('gd') || extension_loaded('imagick'));
    }

    /**
     * M4A-18: Kesiapan operasional tetap berstatus BLOCKED dan penomoran resmi nonaktif.
     */
    public function test_m4a_18_readiness_blocked_and_safety_invariants(): void
    {
        $readiness = app(ReadinessService::class)->evaluateReadiness($this->orgA);

        $this->assertEquals('BLOCKED', $readiness['overall_status']);
        $this->assertLessThanOrEqual(50.0, $readiness['readiness_percentage']);
        $this->assertFalse($readiness['official_numbering_active']);
    }
}
