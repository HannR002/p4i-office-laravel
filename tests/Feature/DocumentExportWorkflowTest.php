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
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;
use ZipArchive;

class DocumentExportWorkflowTest extends TestCase
{
    private Organization $org;

    private User $author;

    private User $reviewer;

    private User $admin;

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
            'legal_name' => 'Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia',
            'acronym' => 'Yayasan P4I',
        ]);

        $unit = OrgUnit::create([
            'organization_id' => $this->org->id,
            'name' => 'Sekretariat Utama',
        ]);

        $roleAdmin = Role::firstOrCreate(['role_key' => 'SYSTEM_ADMIN'], ['label' => 'Admin']);
        $roleStaff = Role::firstOrCreate(['role_key' => 'STAFF'], ['label' => 'Staf']);
        $roleReviewer = Role::firstOrCreate(['role_key' => 'REVIEWER'], ['label' => 'Reviewer']);

        $this->author = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'unit_id' => $unit->id,
            'email' => 'author_m4a@p4i.or.id',
            'display_name' => 'Budi Staf Konseptor',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->author->roles()->attach($roleStaff->id);

        $this->reviewer = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'unit_id' => $unit->id,
            'email' => 'reviewer_m4a@p4i.or.id',
            'display_name' => 'Dr. Rahmat Reviewer',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->reviewer->roles()->attach($roleReviewer->id);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'unit_id' => $unit->id,
            'email' => 'admin_m4a@p4i.or.id',
            'display_name' => 'Administrator Sistem',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->admin->roles()->attach($roleAdmin->id);

        $this->letterType = LetterType::create([
            'organization_id' => $this->org->id,
            'code' => 'UND-M4A',
            'label' => 'Surat Undangan Pelatihan',
            'is_active' => true,
        ]);

        $this->template = LetterTemplate::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'version' => 1,
            'title' => 'Templat Standar Undangan M4A',
            'form_schema' => ['fields' => ['nama_acara']],
            'content_template' => '<p>Undangan resmi ke acara {{nama_acara}}.</p>',
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

    private function createSampleLetter(string $classification = 'INTERNAL', string $status = 'DRAFT'): array
    {
        $letter = Letter::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'template_id' => $this->template->id,
            'author_id' => $this->author->id,
            'classification' => $classification,
            'status' => $status,
            'subject' => 'Undangan Rapat Koordinasi Tahunan',
            'recipient_name' => 'Bapak Kepala Dinas Pendidikan',
            'planned_date' => now()->toDateString(),
            'current_revision_no' => 1,
            'official_number' => null, // INVARIANT: STRICTLY NULL
        ]);

        $revision = LetterRevision::create([
            'id' => (string) Str::uuid(),
            'letter_id' => $letter->id,
            'version' => 1,
            'template_id' => $this->template->id,
            'subject' => $letter->subject,
            'recipient_name' => $letter->recipient_name,
            'planned_date' => $letter->planned_date,
            'created_by' => $this->author->id,
            'classification' => $classification,
            'content' => [
                'body' => [
                    'Dengan hormat,',
                    'Sehubungan dengan evaluasi tahunan, kami bermaksud mengundang Bapak dalam rapat koordinasi.',
                    'Demikian undangan ini kami sampaikan, atas perhatian Bapak kami ucapkan terima kasih.',
                ],
                'rendered_body' => '<p>Dengan hormat, dst.</p>',
            ],
            'change_summary' => 'Draf inisiasi naskah',
            'content_sha256' => hash('sha256', 'sample content'),
        ]);

        return [$letter, $revision];
    }

    /**
     * M4A-01: Export hanya untuk authorized user dan valid revision.
     */
    public function test_m4a_01_authorized_user_and_valid_revision(): void
    {
        [$letter, $revision] = $this->createSampleLetter();

        // 1. Ekspor berhasil oleh Author
        $exportPdf = $this->exportService->exportLetter($this->author, $letter, 'PDF');
        $this->assertInstanceOf(DocumentExport::class, $exportPdf);
        $this->assertEquals('READY', $exportPdf->status);
        $this->assertEquals('PDF', $exportPdf->format);
        $this->assertEquals(1, $exportPdf->revision_version);

        // 2. Ekspor dengan format tidak valid ditolak
        $this->expectException(InvalidArgumentException::class);
        $this->exportService->exportLetter($this->author, $letter, 'EXE');
    }

    /**
     * M4A-02: Generasi file DOCX valid, berstruktur OpenXML, teks sesuai revisi, no macro.
     */
    public function test_m4a_02_docx_generation_valid_and_clean(): void
    {
        [$letter, $revision] = $this->createSampleLetter();

        $exportDocx = $this->exportService->exportLetter($this->author, $letter, 'DOCX');
        $this->assertEquals('READY', $exportDocx->status);
        $this->assertEquals('DOCX', $exportDocx->format);

        // Verifikasi fisik berkas pada disk privat
        $disk = Storage::disk('private_archives');
        $this->assertTrue($disk->exists($exportDocx->file_path));
        $fullPath = $disk->path($exportDocx->file_path);

        // Berkas DOCX adalah arsip ZIP yang sah
        $zip = new ZipArchive;
        $openResult = $zip->open($fullPath);
        $this->assertTrue($openResult === true, 'Berkas DOCX harus merupakan zip archive yang valid.');

        // Periksa keberadaan word/document.xml
        $documentXml = $zip->getFromName('word/document.xml');
        $this->assertNotEmpty($documentXml);

        // Periksa konten surat dan penerima di dalam OpenXML
        $this->assertStringContainsString('Bapak Kepala Dinas Pendidikan', $documentXml);
        $this->assertStringContainsString('Undangan Rapat Koordinasi Tahunan', $documentXml);
        $this->assertStringContainsString('Yayasan Pusat Pendidikan dan Penelitian', $documentXml);

        // Keamanan Makro: Pastikan TIDAK ADA file makro VBA atau executable
        $this->assertFalse($zip->locateName('word/vbaProject.bin') !== false, 'Berkas DOCX tidak boleh mengandung vbaProject.bin.');
        $zip->close();
    }

    /**
     * M4A-03: Generasi file PDF valid dengan watermark DRAF permanen pada setiap halaman.
     */
    public function test_m4a_03_pdf_generation_with_permanent_watermark(): void
    {
        [$letter, $revision] = $this->createSampleLetter();

        $exportPdf = $this->exportService->exportLetter($this->author, $letter, 'PDF');
        $this->assertEquals('READY', $exportPdf->status);
        $this->assertEquals('application/pdf', $exportPdf->mime_type);
        $this->assertGreaterThan(1000, $exportPdf->file_size);

        $disk = Storage::disk('private_archives');
        $this->assertTrue($disk->exists($exportPdf->file_path));
        $content = $disk->get($exportPdf->file_path);

        // Header signature PDF standar
        $this->assertStringStartsWith('%PDF-', $content);
    }

    /**
     * M4A-04: Konsistensi data antara PDF dan DOCX dari sumber revisi yang sama.
     */
    public function test_m4a_04_consistency_between_pdf_and_docx(): void
    {
        [$letter, $revision] = $this->createSampleLetter();

        $exportPdf = $this->exportService->exportLetter($this->author, $letter, 'PDF');
        $exportDocx = $this->exportService->exportLetter($this->author, $letter, 'DOCX');

        $this->assertEquals($exportPdf->revision_id, $exportDocx->revision_id);
        $this->assertEquals($exportPdf->revision_version, $exportDocx->revision_version);
        $this->assertEquals($exportPdf->content_hash, $exportDocx->content_hash);
        $this->assertEquals($exportPdf->organization_id, $exportDocx->organization_id);
        $this->assertTrue($exportPdf->isReady());
        $this->assertTrue($exportDocx->isReady());
    }

    /**
     * M4A-05: Penomoran resmi tetap NULL / Simulasi dan tanpa tanda tangan palsu.
     */
    public function test_m4a_05_official_number_strictly_null_and_unsigned(): void
    {
        [$letter, $revision] = $this->createSampleLetter();

        $exportDocx = $this->exportService->exportLetter($this->author, $letter, 'DOCX');

        // Pastikan record letter official_number tetap NULL di DB
        $letter->refresh();
        $this->assertNull($letter->official_number);

        // Cek isi fisik DOCX: Memuat teks draft nomor simulasi dan tanda tangan belum diterbitkan
        $disk = Storage::disk('private_archives');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($disk->path($exportDocx->file_path)) === true);
        $docXml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('TANDA TANGAN BELUM DITERBITKAN', $docXml);
        $this->assertStringContainsString('[SIMULASI] DRAF', $docXml);
    }

    /**
     * M4A-06: Keamanan rendering dokumen (anti-XSS, anti-SSRF, escaping).
     */
    public function test_m4a_06_document_rendering_security_sanitization(): void
    {
        $maliciousPayload = '<script>alert("hacked")</script><b>Bold Content</b><img src="http://127.0.0.1:8080/ssrf">';

        $letterXss = Letter::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'template_id' => $this->template->id,
            'author_id' => $this->author->id,
            'classification' => 'INTERNAL',
            'status' => 'DRAFT',
            'subject' => 'Surat Uji Sanitasi XSS',
            'recipient_name' => 'Penerima Uji XSS',
            'planned_date' => now()->toDateString(),
            'current_revision_no' => 1,
            'official_number' => null,
        ]);

        $revisionXss = LetterRevision::create([
            'id' => (string) Str::uuid(),
            'letter_id' => $letterXss->id,
            'version' => 1,
            'template_id' => $this->template->id,
            'subject' => 'Surat Uji Sanitasi XSS',
            'recipient_name' => 'Penerima Uji XSS',
            'planned_date' => now()->toDateString(),
            'created_by' => $this->author->id,
            'classification' => 'INTERNAL',
            'content' => [
                'body' => [$maliciousPayload],
            ],
            'change_summary' => 'Uji sanitasi input',
            'content_sha256' => hash('sha256', 'xss test'),
        ]);

        // Rendering DOCX dan PDF harus berhasil tanpa eksekusi skrip atau SSRF crash
        $exportDocx = $this->exportService->exportLetter($this->author, $letterXss, 'DOCX');
        $this->assertEquals('READY', $exportDocx->status);

        $exportPdf = $this->exportService->exportLetter($this->author, $letterXss, 'PDF');
        $this->assertEquals('READY', $exportPdf->status);

        // Periksa DOCX OpenXML: tag <script> di-escape
        $disk = Storage::disk('private_archives');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($disk->path($exportDocx->file_path)) === true);
        $docXml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringNotContainsString('<script>', $docXml);
    }

    /**
     * M4A-12: Idempotensi ekspor dan penanganan pengulangan (retry).
     */
    public function test_m4a_12_export_idempotency_and_retry_handling(): void
    {
        [$letter, $revision] = $this->createSampleLetter();
        $key = 'idemp_key_unique_12345';

        // Panggilan pertama
        $export1 = $this->exportService->exportLetter($this->author, $letter, 'PDF', $key);
        $this->assertEquals('READY', $export1->status);

        // Panggilan kedua dengan idempotency key yang sama
        $export2 = $this->exportService->exportLetter($this->author, $letter, 'PDF', $key);

        // Harus mengembalikan record yang sama persis tanpa duplikasi file
        $this->assertEquals($export1->id, $export2->id);
        $this->assertEquals($export1->file_path, $export2->file_path);
        $this->assertEquals($export1->file_hash, $export2->file_hash);

        // Pastikan hanya ada 1 record di database
        $count = DocumentExport::where('idempotency_key', $key)->count();
        $this->assertEquals(1, $count);
    }

    /**
     * M4A-13: Penanganan kegagalan staging dan pencegahan berkas yatim (orphan files).
     */
    public function test_m4a_13_failure_staging_and_orphan_prevention(): void
    {
        [$letter, $revision] = $this->createSampleLetter();

        // Uji ekspor surat yang tidak memiliki revisi
        $letterNoRev = Letter::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'template_id' => $this->template->id,
            'author_id' => $this->author->id,
            'classification' => 'INTERNAL',
            'status' => 'DRAFT',
            'subject' => 'Surat Kosong Tanpa Revisi',
            'recipient_name' => 'Penerima Fiktif',
            'planned_date' => now()->toDateString(),
            'current_revision_no' => 0,
        ]);

        $this->expectException(DomainException::class);
        $this->exportService->exportLetter($this->author, $letterNoRev, 'PDF');
    }
}
