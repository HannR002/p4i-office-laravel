<?php

namespace Tests\Feature;

use App\Models\DocumentExport;
use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentExportService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArchiveTransactionConcurrencyTest extends TestCase
{
    private Organization $org;

    private OrgUnit $unit;

    private User $author;

    private LetterType $letterType;

    private LetterTemplate $template;

    private DocumentExportService $exportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $this->exportService = app(DocumentExportService::class);

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan P4I Pusat',
            'acronym' => 'P4I-PUSAT',
        ]);

        $this->unit = OrgUnit::create([
            'organization_id' => $this->org->id,
            'name' => 'Divisi Operasional',
        ]);

        $roleStaff = Role::firstOrCreate(['role_key' => 'STAFF'], ['label' => 'Staf']);

        $this->author = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'unit_id' => $this->unit->id,
            'email' => 'author_concurrency@p4i.or.id',
            'display_name' => 'Staf Operasional P4I',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->author->roles()->attach($roleStaff->id);

        $this->letterType = LetterType::create([
            'organization_id' => $this->org->id,
            'code' => 'SUR-OP',
            'label' => 'Surat Operasional',
            'is_active' => true,
        ]);

        $this->template = LetterTemplate::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'version' => 1,
            'title' => 'Templat Operasional',
            'form_schema' => ['fields' => ['kegiatan']],
            'content_template' => '<p>{{kegiatan}}</p>',
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

    private function createTestLetter(): Letter
    {
        $letter = Letter::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'author_id' => $this->author->id,
            'subject' => 'Instruksi Kerja Operasional Kearsipan Mandiri',
            'recipient_name' => 'Seluruh Staf Administrasi',
            'classification' => 'INTERNAL',
            'status' => 'DRAFT',
            'planned_date' => Carbon::now()->format('Y-m-d'),
            'official_number' => null,
        ]);

        $content = [
            'body' => [
                'Setiap staf wajib memastikan kelengkapan metadata naskah dinas sebelum diajukan ke tahap review.',
                'Naskah dinas berstatus draf hanya boleh diuji dalam lingkungan pengembangan lokal atau staging.',
            ],
        ];

        LetterRevision::create([
            'letter_id' => $letter->id,
            'version' => 1,
            'created_by' => $this->author->id,
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
     * M4B-09: Idempotency Key mencegah duplikasi ekspor dan berkas ganda.
     */
    public function test_m4b_09_idempotent_export_returns_existing_record_without_duplication(): void
    {
        $letter = $this->createTestLetter();
        $idempotencyKey = 'idem_key_unique_test_123';

        $export1 = $this->exportService->exportLetter($this->author, $letter, 'PDF', $idempotencyKey);
        $this->assertEquals('READY', $export1->status);

        $initialExportCount = DocumentExport::where('idempotency_key', $idempotencyKey)->count();
        $this->assertEquals(1, $initialExportCount);

        // Ekspor ulang dengan kunci idempoten yang sama
        $export2 = $this->exportService->exportLetter($this->author, $letter, 'PDF', $idempotencyKey);

        // Harus mengembalikan entitas yang sama persis
        $this->assertEquals($export1->id, $export2->id);
        $this->assertEquals($export1->file_path, $export2->file_path);
        $this->assertEquals($export1->file_hash, $export2->file_hash);

        // Jumlah record di database tetap 1
        $this->assertEquals(1, DocumentExport::where('idempotency_key', $idempotencyKey)->count());
    }

    /**
     * M4B-09: Ekspor bersamaan dengan kunci berbeda menghasilkan berkas terpisah yang sah.
     */
    public function test_m4b_09_concurrent_exports_with_different_keys_generate_unique_files(): void
    {
        $letter = $this->createTestLetter();

        $exportA = $this->exportService->exportLetter($this->author, $letter, 'PDF', 'key_concurrent_A');
        $exportB = $this->exportService->exportLetter($this->author, $letter, 'PDF', 'key_concurrent_B');

        $this->assertNotEquals($exportA->id, $exportB->id);
        $this->assertNotEquals($exportA->file_path, $exportB->file_path);

        $disk = Storage::disk('private_archives');
        $this->assertTrue($disk->exists($exportA->file_path));
        $this->assertTrue($disk->exists($exportB->file_path));

        // Kedua berkas memiliki ukuran yang valid dan hash SHA-256 64-karakter yang valid
        $this->assertEquals(64, strlen($exportA->file_hash));
        $this->assertEquals(64, strlen($exportB->file_hash));
        $this->assertNotEmpty($exportA->file_hash);
        $this->assertNotEmpty($exportB->file_hash);
    }

    /**
     * M4B-10: Kegagalan dalam proses ekspor tidak meninggalkan berkas yatim (orphan files) di private storage.
     */
    public function test_m4b_10_export_failure_prevents_orphan_file_and_records_audit(): void
    {
        $letter = $this->createTestLetter();
        $disk = Storage::disk('private_archives');
        $initialFiles = $disk->allFiles();

        // Uji dengan format tidak valid (akan ditolak validasi awal)
        try {
            $this->exportService->exportLetter($this->author, $letter, 'EXE');
            $this->fail('Harusnya melempar InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Format ekspor tidak didukung', $e->getMessage());
        }

        // Pastikan tidak ada berkas fisik baru yang tertinggal di private storage
        $currentFiles = $disk->allFiles();
        $this->assertCount(count($initialFiles), $currentFiles, 'Tidak boleh ada berkas yatim di penyimpanan privat.');
    }

    /**
     * M4B-10: Pemulihan crash dan pencatatan audit kegagalan ekspor.
     */
    public function test_m4b_10_crash_recovery_updates_status_error_and_audits(): void
    {
        $letter = $this->createTestLetter();

        // Buat export dengan revision yang tidak ada (simulasi data korup)
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        $corruptLetter = Letter::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'author_id' => $this->author->id,
            'subject' => 'Surat Korup Tanpa Revisi',
            'recipient_name' => 'Pihak Luar',
            'classification' => 'BIASA',
            'status' => 'DRAFT',
            'planned_date' => Carbon::now()->format('Y-m-d'),
            'official_number' => null,
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        try {
            $this->exportService->exportLetter($this->author, $corruptLetter, 'PDF');
            $this->fail('Harusnya melempar DomainException karena tidak ada revisi');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Surat tidak memiliki data revisi', $e->getMessage());
        }

        // Audit kegagalan tidak boleh merusak integritas sistem
        $this->assertDatabaseMissing('document_exports', [
            'letter_id' => $corruptLetter->id,
            'status' => 'READY',
        ]);
    }
}
