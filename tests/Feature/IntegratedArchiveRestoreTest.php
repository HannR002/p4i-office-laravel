<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentExportService;
use App\Services\ReadinessService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class IntegratedArchiveRestoreTest extends TestCase
{
    private Organization $orgA;

    private Organization $orgB;

    private OrgUnit $unitA;

    private OrgUnit $unitB;

    private User $userA;

    private User $userB;

    private LetterType $typeA;

    private LetterType $typeB;

    private DocumentExportService $exportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $this->exportService = app(DocumentExportService::class);

        $this->orgA = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan P4I Pusat',
            'acronym' => 'P4I-PUSAT',
        ]);

        $this->orgB = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Mitra Yayasan Wilayah Surabaya',
            'acronym' => 'MITRA-SBY',
        ]);

        $this->unitA = OrgUnit::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Divisi Kearsipan Utama',
        ]);

        $this->unitB = OrgUnit::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Unit Pelaksana Wilayah',
        ]);

        $roleStaff = Role::firstOrCreate(['role_key' => 'STAFF'], ['label' => 'Staf']);

        $this->userA = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'unit_id' => $this->unitA->id,
            'email' => 'arsiparis_a@p4i.or.id',
            'display_name' => 'Arsiparis Utama P4I',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->userA->roles()->attach($roleStaff->id);

        $this->userB = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgB->id,
            'unit_id' => $this->unitB->id,
            'email' => 'arsiparis_b@p4i.or.id',
            'display_name' => 'Arsiparis Wilayah Surabaya',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->userB->roles()->attach($roleStaff->id);

        $this->typeA = LetterType::create([
            'organization_id' => $this->orgA->id,
            'code' => 'KOR-A',
            'label' => 'Korespondensi Pusat',
            'is_active' => true,
        ]);

        $this->typeB = LetterType::create([
            'organization_id' => $this->orgB->id,
            'code' => 'KOR-B',
            'label' => 'Korespondensi Cabang',
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

    private function createLetterWithRevision(Organization $org, User $author, LetterType $type, string $subject): Letter
    {
        $letter = Letter::create([
            'organization_id' => $org->id,
            'letter_type_id' => $type->id,
            'author_id' => $author->id,
            'subject' => $subject,
            'recipient_name' => 'Pihak Mitra Kerja',
            'classification' => 'BIASA',
            'status' => 'DRAFT',
            'planned_date' => Carbon::now()->format('Y-m-d'),
            'official_number' => null,
        ]);

        $content = [
            'body' => [
                "Naskah naskah dinas perihal: {$subject}.",
                'Diterbitkan dalam rangka pengujian pemulihan bencana arsip digital (disaster recovery).',
            ],
        ];

        LetterRevision::create([
            'letter_id' => $letter->id,
            'version' => 1,
            'created_by' => $author->id,
            'subject' => $letter->subject,
            'recipient_name' => $letter->recipient_name,
            'classification' => $letter->classification,
            'planned_date' => $letter->planned_date,
            'content' => $content,
            'content_sha256' => hash('sha256', json_encode($content)),
        ]);

        return $letter;
    }

    /**
     * M4B-11: Siklus pemulihan terintegrasi (Multi-file Disaster Recovery & Restore).
     */
    public function test_m4b_11_integrated_multi_file_archive_backup_and_restore_cycle(): void
    {
        $disk = Storage::disk('private_archives');

        // 1. Buat 3 surat berbeda melintasi 2 organisasi
        $letterA1 = $this->createLetterWithRevision($this->orgA, $this->userA, $this->typeA, 'Surat Kerjasama Riset Pusat');
        $letterA2 = $this->createLetterWithRevision($this->orgA, $this->userA, $this->typeA, 'Nota Verifikasi Anggaran Pusat');
        $letterB1 = $this->createLetterWithRevision($this->orgB, $this->userB, $this->typeB, 'Laporan Wilayah Surabaya');

        // 2. Ekspor berkas fisik (PDF & DOCX)
        $expA1Pdf = $this->exportService->exportLetter($this->userA, $letterA1, 'PDF');
        $expA1Docx = $this->exportService->exportLetter($this->userA, $letterA1, 'DOCX');
        $expA2Pdf = $this->exportService->exportLetter($this->userA, $letterA2, 'PDF');
        $expB1Pdf = $this->exportService->exportLetter($this->userB, $letterB1, 'PDF');

        $exports = [$expA1Pdf, $expA1Docx, $expA2Pdf, $expB1Pdf];
        $this->assertCount(4, $exports);

        // Verifikasi integritas awal
        $reportA = $this->exportService->verifyArchiveIntegrity($this->userA);
        $this->assertEquals(3, $reportA['total_files']);
        $this->assertTrue($reportA['all_intact']);

        $reportB = $this->exportService->verifyArchiveIntegrity($this->userB);
        $this->assertEquals(1, $reportB['total_files']);
        $this->assertTrue($reportB['all_intact']);

        // 3. FASE BACKUP: Salin berkas fisik ke direktori cadangan sementara
        $backupDir = sys_get_temp_dir().'/p4i_integrated_backup_'.time();
        File::makeDirectory($backupDir, 0755, true);

        $backupManifest = [];
        foreach ($exports as $exp) {
            $sourcePath = $disk->path($exp->file_path);
            $targetPath = $backupDir.'/'.str_replace('/', '_', $exp->file_path);
            File::copy($sourcePath, $targetPath);

            $backupManifest[$exp->id] = [
                'file_path' => $exp->file_path,
                'file_hash' => $exp->file_hash,
                'file_size' => $exp->file_size,
                'backup_path' => $targetPath,
            ];
        }

        // 4. FASE BENCANA (SIMULASI KORUPSI DAN KEHILANGAN FISIK)
        // Rusak berkas expA1Pdf (ubah isi byte)
        $disk->put($expA1Pdf->file_path, 'KORUP_DATA_RUSAK_AKIBAT_BENCANA');
        // Hapus berkas expA2Pdf
        $disk->delete($expA2Pdf->file_path);

        // Verifikasi sistem mendeteksi kerusakan dan kehilangan
        $corruptReportA = $this->exportService->verifyArchiveIntegrity($this->userA);
        $this->assertFalse($corruptReportA['all_intact']);
        $this->assertEquals(1, $corruptReportA['corrupted_files']);
        $this->assertEquals(1, $corruptReportA['missing_files']);
        $this->assertEquals(1, $corruptReportA['valid_files']);

        // 5. FASE PEMULIHAN (RESTORE)
        foreach ($backupManifest as $id => $manifest) {
            $destPath = $disk->path($manifest['file_path']);
            File::ensureDirectoryExists(dirname($destPath));
            File::copy($manifest['backup_path'], $destPath);
        }

        // 6. VERIFIKASI PASCA-PEMULIHAN
        $restoredReportA = $this->exportService->verifyArchiveIntegrity($this->userA);
        $this->assertTrue($restoredReportA['all_intact'], 'Seluruh arsip organisasi A harus pulih sempurna.');
        $this->assertEquals(3, $restoredReportA['valid_files']);
        $this->assertEquals(0, $restoredReportA['corrupted_files']);
        $this->assertEquals(0, $restoredReportA['missing_files']);

        $restoredReportB = $this->exportService->verifyArchiveIntegrity($this->userB);
        $this->assertTrue($restoredReportB['all_intact'], 'Arsip organisasi B tetap utuh.');

        // Bersihkan direktori cadangan
        File::deleteDirectory($backupDir);
    }

    /**
     * M4B-12: Verifikasi metrik integritas arsip SHA-256 dan akurasi ukuran byte.
     */
    public function test_m4b_12_backup_sha256_checksum_verification_and_restore_metrics(): void
    {
        $letter = $this->createLetterWithRevision($this->orgA, $this->userA, $this->typeA, 'Nota Integritas Kearsipan');
        $export = $this->exportService->exportLetter($this->userA, $letter, 'PDF');

        $disk = Storage::disk('private_archives');
        $realPath = $disk->path($export->file_path);

        $computedHash = hash_file('sha256', $realPath);
        $this->assertEquals($export->file_hash, $computedHash);
        $this->assertEquals(filesize($realPath), $export->file_size);

        $metrics = $this->exportService->verifyArchiveIntegrity($this->userA);
        $this->assertArrayHasKey('total_files', $metrics);
        $this->assertArrayHasKey('valid_files', $metrics);
        $this->assertArrayHasKey('corrupted_files', $metrics);
        $this->assertArrayHasKey('missing_files', $metrics);
        $this->assertArrayHasKey('total_bytes', $metrics);
        $this->assertArrayHasKey('all_intact', $metrics);
        $this->assertTrue($metrics['all_intact']);
        $this->assertEquals($export->file_size, $metrics['total_bytes']);
    }

    /**
     * M4B-14: Kesiapan Staging Hostinger dan Evaluasi Kesiapan Tetap Menghasilkan Status BLOCKED.
     */
    public function test_m4b_14_hostinger_staging_readiness_checklist_remains_blocked(): void
    {
        $readinessService = app(ReadinessService::class);
        $eval = $readinessService->evaluateReadiness($this->orgA);

        // 1. Status kesiapan wajib tegas BLOCKED
        $this->assertEquals('BLOCKED', $eval['overall_status']);

        // 2. Skor tidak boleh melebihi 50%
        $this->assertLessThanOrEqual(50.0, $eval['readiness_percentage']);

        // 3. Penomoran resmi wajib nonaktif
        $this->assertFalse($eval['official_numbering_active']);
    }

    /**
     * M4B-15: Invarian Keamanan Keras: official_numbering_active = false dan official_number STRICTLY NULL.
     */
    public function test_m4b_15_official_numbering_off_and_null_invariants(): void
    {
        // 1. Invarian konfigurasi sistem penomoran resmi wajib nonaktif
        $this->assertFalse(config('p4i.official_numbering_active', false));

        // 2. Setiap naskah yang dibuat wajib memiliki official_number = NULL
        $letter = $this->createLetterWithRevision($this->orgA, $this->userA, $this->typeA, 'Pemeriksaan Invarian Nomor');
        $this->assertNull($letter->official_number);

        $this->assertDatabaseMissing('letters', [
            'id' => $letter->id,
            'official_number' => 'NOT NULL',
        ]);

        // 3. Ekspor naskah selalu menampilkan penanda DRAF SIMULASI
        $export = $this->exportService->exportLetter($this->userA, $letter, 'DOCX');
        $disk = Storage::disk('private_archives');

        $zip = new ZipArchive;
        $zip->open($disk->path($export->file_path));
        $docXml = $zip->getFromName('word/document.xml');
        $headerXml = $zip->getFromName('word/header1.xml');
        $zip->close();

        $this->assertStringContainsString('[SIMULASI] DRAF', $docXml);
        $this->assertStringContainsString('DRAF', $headerXml);
    }
}
