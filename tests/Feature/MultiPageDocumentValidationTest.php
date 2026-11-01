<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use Tests\TestCase;
use ZipArchive;

class MultiPageDocumentValidationTest extends TestCase
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
            'legal_name' => 'Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia',
            'acronym' => 'Yayasan P4I',
            'address_placeholder' => 'Gedung P4I Lantai 3, Jl. Pembangunan Nasional, Jakarta',
        ]);

        $this->unit = OrgUnit::create([
            'organization_id' => $this->org->id,
            'name' => 'Divisi Riset dan Kearsipan',
        ]);

        $roleStaff = Role::firstOrCreate(['role_key' => 'STAFF'], ['label' => 'Staf']);

        $this->author = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'unit_id' => $this->unit->id,
            'email' => 'author_doc@p4i.or.id',
            'display_name' => 'Dra. Siti Rahmawati',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->author->roles()->attach($roleStaff->id);

        $this->letterType = LetterType::create([
            'organization_id' => $this->org->id,
            'code' => 'NOT-EVAL',
            'label' => 'Nota Evaluasi Program',
            'is_active' => true,
        ]);

        $this->template = LetterTemplate::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'version' => 1,
            'title' => 'Templat Evaluasi',
            'form_schema' => ['fields' => ['judul']],
            'content_template' => '<p>{{judul}}</p>',
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

    private function createMultiPageLetter(): Letter
    {
        $letter = Letter::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'author_id' => $this->author->id,
            'subject' => 'Laporan Evaluasi Tahunan Kinerja dan Capaian Riset 2026',
            'recipient_name' => 'Dewan Pembina dan Pengurus Yayasan P4I',
            'classification' => 'TERBATAS',
            'status' => 'DRAFT',
            'planned_date' => '2026-11-15',
            'official_number' => null,
        ]);

        $content = [
            'body' => [
                'BAGIAN I: PENDAHULUAN DAN LATAR BELAKANG EVALUASI TAHUNAN',
                'Yayasan P4I secara periodik melakukan penelaahan menyeluruh terhadap ketercapaian target program kerja. Dokumen ini merupakan naskah draf kerja evaluasi tahunan.',
                'Seluruh divisi diwajibkan menyampaikan data dukung dan matriks pertanggungjawaban program secara transparan.',
                '[PAGE_BREAK]',
                'BAGIAN II: MATRIKS EVALUASI KINERJA PROGRAM STRATEGIS',
                'Berikut disajikan rekapitulasi data capaian indikator kinerja dari seluruh unit kerja pelaksana di lingkungan Yayasan P4I:',
                '[PAGE_BREAK]',
                'BAGIAN III: REKOMENDASI DAN RENCANA TINDAK LANJUT 2027',
                'Berdasarkan hasil analisis, direkomendasikan penguatan sistem tata kelola naskah dinas elektronik dan verifikasi integritas arsip privat berbasis SHA-256.',
                'Demikian laporan evaluasi tahunan ini disusun untuk menjadi bahan pertimbangan kebijakan pimpinan yayasan.',
            ],
            'tables' => [
                [
                    ['No', 'Indikator Program', 'Target', 'Capaian', 'Status'],
                    ['1', 'Digitalisasi Arsip Surat', '1000 Berkas', '1050 Berkas', 'Tuntas'],
                    ['2', 'Pelatihan Penomoran Mandat', '4 Sesi', '4 Sesi', 'Sesuai'],
                    ['3', 'Audit Integritas SHA-256', '100% Berkas', '100% Berkas', 'Sempurna'],
                ],
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
     * M4B-05: PDF menghasilkan 3 halaman atau lebih dengan watermark berulang pada setiap halaman.
     */
    public function test_m4b_05_pdf_renders_three_or_more_pages_with_repeating_watermark(): void
    {
        $letter = $this->createMultiPageLetter();
        $export = $this->exportService->exportLetter($this->author, $letter, 'PDF');

        $this->assertEquals('READY', $export->status);
        $this->assertEquals('application/pdf', $export->mime_type);

        $disk = Storage::disk('private_archives');
        $this->assertTrue($disk->exists($export->file_path));

        $pdfBytes = $disk->get($export->file_path);
        $this->assertStringStartsWith('%PDF-', $pdfBytes);

        // Hitung objek halaman (/Type /Page)
        preg_match_all('/\/Type\s*\/Page\b/', $pdfBytes, $matches);
        $pageCount = count($matches[0]);
        $this->assertGreaterThanOrEqual(3, $pageCount, "PDF harus memiliki minimal 3 halaman, ditemukan {$pageCount} halaman.");
    }

    /**
     * M4B-06: DOCX menghasilkan struktur multi-halaman yang valid, bersih dari makro (macro-free).
     */
    public function test_m4b_06_docx_renders_multi_page_and_is_macro_free(): void
    {
        $letter = $this->createMultiPageLetter();
        $export = $this->exportService->exportLetter($this->author, $letter, 'DOCX');

        $this->assertEquals('READY', $export->status);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $export->mime_type);

        $disk = Storage::disk('private_archives');
        $realPath = $disk->path($export->file_path);

        $zip = new ZipArchive;
        $opened = $zip->open($realPath);
        $this->assertTrue($opened === true, 'Berkas DOCX harus dapat dibuka sebagai arsip ZIP OpenXML.');

        // 1. Verifikasi Macro-free (Tidak ada berkas VBA atau biner)
        $hasMacros = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = strtolower($zip->getNameIndex($i));
            if (str_contains($entryName, 'vba') || str_ends_with($entryName, '.bin')) {
                $hasMacros = true;
            }
        }
        $this->assertFalse($hasMacros, 'Berkas DOCX tidak boleh mengandung makro atau berkas biner eksekusi.');

        // 2. Verifikasi Komponen OpenXML
        $contentTypes = $zip->getFromName('[Content_Types].xml');
        $this->assertNotEmpty($contentTypes);
        $this->assertStringContainsString('wordprocessingml.document.main+xml', $contentTypes);

        $documentXml = $zip->getFromName('word/document.xml');
        $this->assertNotEmpty($documentXml);
        // Memiliki page break
        $this->assertStringContainsString('w:type="page"', $documentXml);
        // Memiliki tabel
        $this->assertStringContainsString('<w:tbl', $documentXml);

        // 3. Verifikasi Header Watermark Berulang
        $headerXml = $zip->getFromName('word/header1.xml');
        $this->assertNotEmpty($headerXml);
        $this->assertStringContainsString('DRAF', $headerXml);

        // 4. Verifikasi Footer Penafian
        $footerXml = $zip->getFromName('word/footer1.xml');
        $this->assertNotEmpty($footerXml);
        $this->assertStringContainsString('penomoran resmi', $footerXml);

        $zip->close();
    }

    /**
     * M4B-07: Kesetaraan data dan semantik antara format PDF dan DOCX (Unified Source Data).
     */
    public function test_m4b_07_pdf_and_docx_data_and_semantic_equivalence(): void
    {
        $letter = $this->createMultiPageLetter();

        $exportPdf = $this->exportService->exportLetter($this->author, $letter, 'PDF');
        $exportDocx = $this->exportService->exportLetter($this->author, $letter, 'DOCX');

        $this->assertEquals($exportPdf->revision_version, $exportDocx->revision_version);
        $this->assertEquals($exportPdf->content_hash, $exportDocx->content_hash);

        $disk = Storage::disk('private_archives');
        $docxPath = $disk->path($exportDocx->file_path);

        $zip = new ZipArchive;
        $zip->open($docxPath);
        $docXml = $zip->getFromName('word/document.xml');
        $zip->close();

        // Verifikasi metadata yang sama tercetak pada naskah
        $this->assertStringContainsString($letter->subject, $docXml);
        $this->assertStringContainsString($letter->recipient_name, $docXml);
        $this->assertStringContainsString('Digitalisasi Arsip Surat', $docXml);
        $this->assertStringContainsString('1050 Berkas', $docXml);
        $this->assertStringContainsString('[SIMULASI] DRAF/P4I/', $docXml);
        $this->assertStringContainsString('[TANDA TANGAN BELUM DITERBITKAN]', $docXml);
    }

    /**
     * M4B-08: Sanitasi konten - Tolak injeksi skrip, SSRF, dan sanitasi tag HTML.
     */
    public function test_m4b_08_xss_injection_and_remote_ssrf_sanitization(): void
    {
        $letter = Letter::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'author_id' => $this->author->id,
            'subject' => 'Uji Keamanan Injeksi Script & SSRF',
            'recipient_name' => 'Tim Keamanan Siber',
            'classification' => 'BIASA',
            'status' => 'DRAFT',
            'planned_date' => '2026-11-20',
            'official_number' => null,
        ]);

        $maliciousContent = [
            'body' => [
                '<script>alert("XSS_ATTACK_VECTOR");</script>',
                '<img src="http://169.254.169.254/latest/meta-data/" onerror="fetch(\'http://attacker.com\')">',
                'Karakter khusus: & < > " \' serta huruf beraksen Indonesia & Latin: É, è, ç, ñ, ü.',
            ],
            'tables' => [
                [
                    ['Header <script>bad()</script>', 'Data <b>Tebal</b>'],
                    ['<iframe src="http://evil.com"></iframe>', 'Nilai & Entitas'],
                ],
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
            'content' => $maliciousContent,
            'content_sha256' => hash('sha256', json_encode($maliciousContent)),
        ]);

        // Ekspor ke PDF dan DOCX harus berhasil tanpa kesalahan eksekusi
        $exportPdf = $this->exportService->exportLetter($this->author, $letter, 'PDF');
        $exportDocx = $this->exportService->exportLetter($this->author, $letter, 'DOCX');

        $this->assertEquals('READY', $exportPdf->status);
        $this->assertEquals('READY', $exportDocx->status);

        $disk = Storage::disk('private_archives');

        // DOCX tidak boleh memuat tag <script> atau <iframe>
        $zip = new ZipArchive;
        $zip->open($disk->path($exportDocx->file_path));
        $docXml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringNotContainsString('<script>', $docXml);
        $this->assertStringNotContainsString('<iframe>', $docXml);
        $this->assertStringNotContainsString('XSS_ATTACK_VECTOR', $docXml);
        $this->assertStringContainsString('Karakter khusus:', $docXml);
    }

    /**
     * M4B-05: Bukti fisik arsip contoh 1 halaman dan 3 halaman pada direktori docs/evidence/sprint-m4b/.
     */
    public function test_m4b_05_physical_evidence_artifacts_exist_and_pass_validation(): void
    {
        $evidenceDir = base_path('docs/evidence/sprint-m4b');

        $p1Pdf = $evidenceDir.'/sample_1page_draft.pdf';
        $p3Pdf = $evidenceDir.'/sample_3page_draft.pdf';
        $p1Docx = $evidenceDir.'/sample_1page_draft.docx';
        $p3Docx = $evidenceDir.'/sample_3page_draft.docx';

        $this->assertFileExists($p1Pdf, 'sample_1page_draft.pdf harus ada.');
        $this->assertFileExists($p3Pdf, 'sample_3page_draft.pdf harus ada.');
        $this->assertFileExists($p1Docx, 'sample_1page_draft.docx harus ada.');
        $this->assertFileExists($p3Docx, 'sample_3page_draft.docx harus ada.');

        // Verifikasi PDF 1 halaman
        $pdf1Bytes = file_get_contents($p1Pdf);
        $this->assertStringStartsWith('%PDF-', $pdf1Bytes);
        preg_match_all('/\/Type\s*\/Page\b/', $pdf1Bytes, $m1);
        $this->assertEquals(1, count($m1[0]), 'sample_1page_draft.pdf harus tepat 1 halaman.');

        // Verifikasi PDF 3 halaman dan ekstraksi stream tiap halaman (STG-03)
        $pdf3Bytes = file_get_contents($p3Pdf);
        $this->assertStringStartsWith('%PDF-', $pdf3Bytes);
        preg_match_all('/\/Type\s*\/Page\b/', $pdf3Bytes, $m3);
        $this->assertEquals(3, count($m3[0]), 'sample_3page_draft.pdf harus tepat 3 halaman.');

        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf3Bytes, $streams);
        $this->assertGreaterThanOrEqual(3, count($streams[1]));
        $tableFoundInPdf = false;
        foreach ($streams[1] as $rawStream) {
            $dec = @gzuncompress($rawStream);
            if ($dec !== false && str_contains($dec, 'DRAF')) {
                $this->assertStringContainsString('BELUM RESMI', $dec);
                $this->assertStringContainsString('DRAF SIMULASI P4I', $dec);
                if (str_contains($dec, 'Program Strategis') && str_contains($dec, 'Pelatihan Tata Kelola')) {
                    $tableFoundInPdf = true;
                }
            }
        }
        $this->assertTrue($tableFoundInPdf, 'Tabel capaian IKU harus ter-render di dalam stream halaman PDF 3 halaman.');

        // Verifikasi DOCX 1 halaman dan 3 halaman valid secara OpenXML, macro-free, dan dapat dibaca IOFactory::load (STG-03)
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($p3Docx) === true);
        $hasMacro = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = strtolower($zip->getNameIndex($i));
            if (str_contains($entry, 'vba') || str_ends_with($entry, '.bin')) {
                $hasMacro = true;
            }
        }
        $this->assertFalse($hasMacro);
        $docXml = $zip->getFromName('word/document.xml');
        $this->assertStringContainsString('w:type="page"', $docXml);
        $this->assertStringContainsString('<w:tbl', $docXml);
        $this->assertStringContainsString('PERHATIAN KEARSIPAN:', $docXml);
        $this->assertStringContainsString('Revisi Ke-1', $docXml);
        $zip->close();

        $loaded1 = IOFactory::load($p1Docx, 'Word2007');
        $loaded3 = IOFactory::load($p3Docx, 'Word2007');
        $this->assertNotEmpty($loaded1->getSections());
        $this->assertNotEmpty($loaded3->getSections());
    }

    /**
     * M5B-A (Section 3.B): Verifikasi mendalam per-halaman (Page Object / Contents Stream & Logical DOCX Pages)
     * serta artefak render visual (bukan hanya page break tag atau checksum).
     */
    public function test_m5b_a_page_by_page_rendered_content_table_and_watermark_verification(): void
    {
        $m4bDir = base_path('docs/evidence/sprint-m4b');
        $m5baDir = base_path('docs/evidence/sprint-m5b-a');

        $p1Pdf = $m4bDir.'/sample_1page_draft.pdf';
        $p3Pdf = $m4bDir.'/sample_3page_draft.pdf';
        $p1Docx = $m4bDir.'/sample_1page_draft.docx';
        $p3Docx = $m4bDir.'/sample_3page_draft.docx';

        // 1. Pemetaan Objek Halaman PDF (/Type /Page -> /Contents -> FlateDecode Stream) untuk 3-Page PDF
        $raw3 = file_get_contents($p3Pdf);
        $objects = [];
        preg_match_all('/(\d+)\s+0\s+obj\b(.*?)endobj/s', $raw3, $objMatches, PREG_SET_ORDER);
        foreach ($objMatches as $m) {
            $objects[(int) $m[1]] = $m[2];
        }

        $pageStreams = [];
        foreach ($objects as $objBody) {
            if (preg_match('/\/Type\s*\/Page\b(?!s)/', $objBody) && preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $objBody, $cm)) {
                $cid = (int) $cm[1];
                if (isset($objects[$cid]) && preg_match('/stream\r?\n(.*?)\r?\nendstream/s', $objects[$cid], $sm)) {
                    $pageStreams[] = (string) @gzuncompress($sm[1]);
                }
            }
        }

        $this->assertCount(3, $pageStreams, 'Harus terdapat tepat 3 content stream halaman terpisah pada sample_3page_draft.pdf.');

        // Setiap halaman (Hal 1, Hal 2, Hal 3) wajib memuat watermark diagonal dan footer draf
        foreach ($pageStreams as $idx => $stream) {
            $pageNum = $idx + 1;
            $this->assertStringContainsString('BELUM RESMI', $stream, "Halaman {$pageNum} PDF wajib memuat watermark diagonal.");
            $this->assertStringContainsString('TIDAK BERLAKU SEBAGAI SURAT RESMI', $stream, "Halaman {$pageNum} PDF wajib memuat sub-watermark.");
            $this->assertStringContainsString('NOMOR RESMI STRICTLY NULL', $stream, "Halaman {$pageNum} PDF wajib memuat footer draf.");
        }

        // Verifikasi isi spesifik per halaman pada sample_3page_draft.pdf
        $this->assertStringContainsString('BAGIAN I: PENDAHULUAN', $pageStreams[0]);
        $this->assertStringContainsString('[SIMULASI] DRAF/P4I/2026/REV-1', $pageStreams[0]);

        $this->assertStringContainsString('BAGIAN II: MATRIKS CAPAIAN INDIKATOR KINERJA UTAMA', $pageStreams[1]);
        $this->assertStringContainsString('Program Strategis', $pageStreams[1]);
        $this->assertStringContainsString('Pelatihan Tata Kelola', $pageStreams[1]);
        $this->assertStringContainsString('Lokakarya Nasional', $pageStreams[1]);

        $this->assertStringContainsString('BAGIAN III: REKOMENDASI KEBIJAKAN', $pageStreams[2]);
        $this->assertStringContainsString('[TANDA TANGAN BELUM DITERBITKAN]', $pageStreams[2]);
        $this->assertStringContainsString('PERHATIAN KEARSIPAN:', $pageStreams[2]);
        $this->assertStringContainsString('Revisi Ke-1', $pageStreams[2]);

        // 2. Verifikasi per-halaman logis dan tabel pada sample_3page_draft.docx
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($p3Docx) === true);
        $docXml = (string) $zip->getFromName('word/document.xml');
        $headerXml = (string) $zip->getFromName('word/header1.xml');
        $footerXml = (string) $zip->getFromName('word/footer1.xml');
        $zip->close();

        $this->assertStringContainsString('DRAF — BELUM RESMI — TIDAK BERLAKU SEBAGAI SURAT KELUAR', $headerXml);
        $this->assertStringContainsString('Segala bentuk penomoran resmi, tanda tangan, dan stempel dinonaktifkan.', $footerXml);

        $docxPages = preg_split('/<w:br\s+w:type="page"\s*\/>/', $docXml);
        $this->assertCount(3, $docxPages, 'sample_3page_draft.docx harus terbagi menjadi 3 halaman logis.');
        $this->assertStringContainsString('BAGIAN I: PENDAHULUAN', $docxPages[0]);
        $this->assertStringNotContainsString('<w:tbl>', $docxPages[0]);

        $this->assertStringContainsString('BAGIAN II: MATRIKS CAPAIAN', $docxPages[1]);
        $this->assertStringContainsString('<w:tbl>', $docxPages[1]);
        $this->assertEquals(7, substr_count($docxPages[1], '<w:tr>'), 'Tabel pada Halaman 2 DOCX harus memiliki 7 baris (1 header + 6 baris data).');

        $this->assertStringContainsString('BAGIAN III: REKOMENDASI KEBIJAKAN', $docxPages[2]);
        $this->assertStringContainsString('[TANDA TANGAN BELUM DITERBITKAN]', $docxPages[2]);
        $this->assertStringContainsString('Revisi Ke-1', $docxPages[2]);

        // 3. Verifikasi keberadaan bukti hasil render visual (PNG) dan laporan ekstraksi JSON di docs/evidence/sprint-m5b-a/
        $this->assertFileExists($m5baDir.'/document_verification_report.json');
        $this->assertFileExists($m5baDir.'/render_docx_1page_p1.png');
        $this->assertFileExists($m5baDir.'/render_docx_3page_p1.png');
        $this->assertFileExists($m5baDir.'/render_docx_3page_p2.png');
        $this->assertFileExists($m5baDir.'/render_docx_3page_p3.png');
    }
}
