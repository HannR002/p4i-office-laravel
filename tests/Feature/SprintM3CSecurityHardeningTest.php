<?php

namespace Tests\Feature;

use App\Models\LegacyRegisterBatch;
use App\Models\LegacyRegisterRow;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\LegacyRegisterService;
use App\Services\ReadinessService;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;
use ZipArchive;

class SprintM3CSecurityHardeningTest extends TestCase
{
    private Organization $org;

    private User $admin;

    private User $reviewer;

    private User $staff;

    private User $pengurus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan P4I Security Test',
            'acronym' => 'P4I-SEC',
        ]);

        $roleAdmin = Role::firstOrCreate(['role_key' => 'SYSTEM_ADMIN'], ['label' => 'Administrator']);
        $roleReviewer = Role::firstOrCreate(['role_key' => 'REVIEWER'], ['label' => 'Reviewer']);
        $roleStaff = Role::firstOrCreate(['role_key' => 'STAFF'], ['label' => 'Staff']);
        $rolePengurus = Role::firstOrCreate(['role_key' => 'PENGURUS_YAYASAN'], ['label' => 'Pengurus Yayasan']);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'admin_sec@p4i.or.id',
            'display_name' => 'Admin Security',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);
        $this->admin->roles()->attach($roleAdmin->id);

        $this->reviewer = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'reviewer_sec@p4i.or.id',
            'display_name' => 'Reviewer Security',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);
        $this->reviewer->roles()->attach($roleReviewer->id);

        $this->staff = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'staff_sec@p4i.or.id',
            'display_name' => 'Staff Security',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);
        $this->staff->roles()->attach($roleStaff->id);

        $this->pengurus = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'pengurus_sec@p4i.or.id',
            'display_name' => 'Ketua Yayasan',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);
        $this->pengurus->roles()->attach($rolePengurus->id);
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
            'reconciliation_audit_logs',
            'legacy_register_rows',
            'legacy_register_batches',
            'agenda_policies',
            'incoming_letters',
            'letters',
            'users',
            'organizations',
        ];
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Helper untuk membuat file ZIP / XLSX sintetis dalam direktori sementara.
     */
    private function createSyntheticXlsx(string $sheetXml, ?string $sharedStringsXml = null, array $extraFiles = []): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'sec_xlsx_').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        if ($sharedStringsXml !== null) {
            $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        }
        foreach ($extraFiles as $fname => $content) {
            $zip->addFromString($fname, $content);
        }
        $zip->close();

        return new UploadedFile($tempPath, 'register_test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * CR-01, SC-02, SC-03: Penolakan deklarasi DOCTYPE, ENTITY, dan substitusi entitas XML (XXE).
     */
    public function test_sc_02_and_sc_03_xlsx_rejects_doctype_and_entity_declarations(): void
    {
        $maliciousXml = '<?xml version="1.0"?>'.
            '<!DOCTYPE root [<!ENTITY xxe "evil_payload">]>'.
            '<worksheet><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>&xxe;</t></is></c></row></sheetData></worksheet>';

        $file = $this->createSyntheticXlsx($maliciousXml);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dokumen XML memuat DTD atau deklarasi entitas yang dilarang demi keamanan');

        $service = app(LegacyRegisterService::class);
        $service->importFile($this->staff, $file);
    }

    /**
     * SC-03: Penolakan entitas eksternal berbasis SYSTEM atau PUBLIC.
     */
    public function test_sc_03_xlsx_rejects_system_and_public_external_entities(): void
    {
        $maliciousXml = '<?xml version="1.0"?>'.
            '<worksheet xmlns:x="SYSTEM \"file:///etc/passwd\""><sheetData></sheetData></worksheet>';

        $file = $this->createSyntheticXlsx($maliciousXml);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dokumen XML memuat referensi entitas eksternal yang dilarang');

        $service = app(LegacyRegisterService::class);
        $service->importFile($this->staff, $file);
    }

    /**
     * SC-04: Penolakan path traversal dalam struktur berkas ZIP XLSX.
     */
    public function test_sc_04_zip_path_traversal_rejected(): void
    {
        $sheetXml = '<?xml version="1.0"?><worksheet><sheetData></sheetData></worksheet>';
        $file = $this->createSyntheticXlsx($sheetXml, null, [
            '../../evil_script.sh' => 'echo hacked',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nama entri berkas terindikasi path traversal terlarang');

        $service = app(LegacyRegisterService::class);
        $service->importFile($this->staff, $file);
    }

    /**
     * SC-04: Batas jumlah entri berkas dalam arsip ZIP (Maksimal 100 entri).
     */
    public function test_sc_04_zip_entry_count_limit_enforced(): void
    {
        $sheetXml = '<?xml version="1.0"?><worksheet><sheetData></sheetData></worksheet>';
        $extraFiles = [];
        for ($i = 0; $i < 105; $i++) {
            $extraFiles["dummy_{$i}.txt"] = 'data';
        }

        $file = $this->createSyntheticXlsx($sheetXml, null, $extraFiles);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Jumlah berkas internal ZIP melebihi batas keamanan maksimum');

        $service = app(LegacyRegisterService::class);
        $service->importFile($this->staff, $file);
    }

    /**
     * SC-05: Penolakan komponen Macro, VBA, berkas binari eksekusi, dan pranala eksternal.
     */
    public function test_sc_05_macro_vba_and_external_links_rejected(): void
    {
        $sheetXml = '<?xml version="1.0"?><worksheet><sheetData></sheetData></worksheet>';

        // 1. Uji komponen Macro / vbaProject.bin
        $macroFile = $this->createSyntheticXlsx($sheetXml, null, [
            'xl/vbaProject.bin' => 'MALICIOUS_VBA_CODE',
        ]);

        try {
            $service = app(LegacyRegisterService::class);
            $service->importFile($this->staff, $macroFile);
            $this->fail('Harusnya melempar exception komponen terlarang');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('komponen terlarang', $e->getMessage());
        }

        // 2. Uji komponen Pranala Luar / External Links
        $extLinkFile = $this->createSyntheticXlsx($sheetXml, null, [
            'xl/externalLinks/externalLink1.xml' => '<externalLink/>',
        ]);

        try {
            $service->importFile($this->staff, $extLinkFile);
            $this->fail('Harusnya melempar exception pranala luar');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('pranala luar', $e->getMessage());
        }
    }

    /**
     * SC-04 & SC-06: Batas sumber daya data grid (Maksimal 5.000 baris, 50 kolom, 1.000 karakter/sel).
     */
    public function test_sc_04_grid_limits_enforced_on_oversized_cells(): void
    {
        // Sel teks dengan panjang melampaui 1.000 karakter
        $oversizedText = str_repeat('A', 1050);
        $sheetXml = '<?xml version="1.0"?><worksheet><sheetData>'.
            '<row r="1"><c r="A1" t="inlineStr"><is><t>'.$oversizedText.'</t></is></c></row>'.
            '</sheetData></worksheet>';

        $file = $this->createSyntheticXlsx($sheetXml);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Panjang teks dalam sel melebihi batas maksimum 1.000 karakter');

        $service = app(LegacyRegisterService::class);
        $service->importFile($this->staff, $file);
    }

    /**
     * SC-07 & SC-08: Segregation of Duties (SoD) pada rekonsiliasi arsip:
     * 1. Larangan self-approval bagi pengusul rekonsiliasi.
     * 2. Administrator teknis dilarang mengesahkan arsip tanpa SK Dewan Pengurus.
     * 3. Sifat append-only di mana nomor mentah asli tetap utuh.
     */
    public function test_sc_07_and_sc_08_legacy_reconciliation_sod_and_immutability(): void
    {
        $batch = LegacyRegisterBatch::create([
            'organization_id' => $this->org->id,
            'source_filename' => 'register_riil_2024.csv',
            'source_hash' => hash('sha256', 'sample'),
            'total_rows' => 1,
            'valid_rows' => 1,
            'conflict_rows' => 0,
            'status' => 'STAGED',
            'is_sample' => false,
            'imported_by' => $this->staff->id,
        ]);

        $row = LegacyRegisterRow::create([
            'batch_id' => $batch->id,
            'row_index' => 1,
            'raw_number' => '042/P4I/III/2024',
            'normalized_number' => '042/P4I/III/2024',
            'document_date' => '2024-03-10',
            'letter_type_code' => 'SURAT_KELUAR',
            'recipient' => 'Bappeda Jawa Barat',
            'subject' => 'Kerjasama Riset',
            'source_location' => 'Buku Register Fisik No. 04 Hal. 15',
            'provenance_year' => 2024,
            'provenance_series' => 'SURAT_KELUAR',
            'is_valid' => true,
            'verification_status' => 'UNVERIFIED',
            'proposed_by' => $this->staff->id, // Diusulkan oleh staf
        ]);

        $service = app(LegacyRegisterService::class);

        // 1. Pengusul dilarang memverifikasi sendiri (SoD Self-Approval)
        try {
            $service->reconcileRow($this->staff, $row, 'VERIFIED', 'RETAIN_AS_IS', 'Saya menyetujui usulan saya');
            $this->fail('Pengusul harusnya dilarang memverifikasi sendiri');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Pengusul perubahan register dilarang memverifikasi keputusannya sendiri', $e->getMessage());
        }

        // 2. Administrator teknis dilarang mengesahkan arsip tanpa SK Dewan Pengurus (SoD Admin Wewenang)
        try {
            $service->reconcileRow($this->admin, $row, 'VERIFIED', 'RETAIN_AS_IS', 'Admin mengesahkan tanpa SK', null);
            $this->fail('Admin teknis harusnya dilarang mengesahkan tanpa SK Dewan Pengurus');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Administrator teknis tidak memiliki kewenangan yuridis', $e->getMessage());
        }

        // 3. Pengurus Yayasan dengan SK sah diizinkan mengesahkan
        $reconciledRow = $service->reconcileRow(
            $this->pengurus,
            $row,
            'VERIFIED',
            'RETAIN_AS_IS',
            'Diverifikasi autentik oleh Rapat Pengurus',
            'SK-YAYASAN-01/XI/2026'
        );

        $this->assertEquals('VERIFIED', $reconciledRow->verification_status);
        $this->assertEquals('042/P4I/III/2024', $reconciledRow->raw_number); // raw_number tidak berubah (immutable)

        // Verifikasi log audit mencatat pengusul dan pemeriksa secara terpisah
        $this->assertDatabaseHas('reconciliation_audit_logs', [
            'row_id' => $row->id,
            'user_id' => $this->pengurus->id,
            'proposer_id' => $this->staff->id,
            'verifier_id' => $this->pengurus->id,
            'board_resolution_ref' => 'SK-YAYASAN-01/XI/2026',
            'action_taken' => 'RETAIN_AS_IS',
        ]);
    }

    /**
     * SC-12 & SC-14: Kesiapan persuratan wajib BLOCKED selama prasyarat administratif belum lengkap.
     * Batch sampel tidak dihitung sebagai rekonsiliasi arsip asli.
     */
    public function test_sc_12_and_sc_14_readiness_blocked_and_sample_isolation(): void
    {
        // 1. Buat batch yang bertanda is_sample = true (seperti dari DatabaseSeeder)
        LegacyRegisterBatch::create([
            'organization_id' => $this->org->id,
            'source_filename' => 'rekap_sampel.csv',
            'source_hash' => hash('sha256', 'sample'),
            'total_rows' => 1,
            'valid_rows' => 1,
            'conflict_rows' => 0,
            'status' => 'VERIFIED',
            'is_sample' => true, // Sampel!
            'imported_by' => $this->staff->id,
        ]);

        $readinessService = app(ReadinessService::class);
        $report = $readinessService->evaluateReadiness($this->org);

        // Status wajib BLOCKED
        $this->assertEquals('BLOCKED', $report['overall_status']);

        // Pilar 2 (LEGACY_RECONCILIATION) harus BLOCKED karena hanya ada data sampel!
        $pilar2 = collect($report['pillars'])->firstWhere('key', 'LEGACY_RECONCILIATION');
        $this->assertEquals('BLOCKED', $pilar2['status']);
        $this->assertFalse($pilar2['passed']);
        $this->assertStringContainsString('Hanya terdapat data sampel pengujian', $pilar2['details']);

        // Pilar 7 (HOSTINGER_ENVIRONMENT_CHECK) harus BLOCKED karena belum ada staging yang diizinkan!
        $pilar7 = collect($report['pillars'])->firstWhere('key', 'HOSTINGER_ENVIRONMENT_CHECK');
        $this->assertEquals('BLOCKED', $pilar7['status']);
        $this->assertFalse($pilar7['passed']);
        $this->assertStringContainsString('Pengujian staging Hostinger belum diizinkan', $pilar7['details']);
    }
}
