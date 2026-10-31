<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\DocumentExport;
use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentExportService
{
    public const DISK = 'private_archives';

    /**
     * Mengekspor dokumen naskah dinas berformat DRAF PDF atau DOCX dengan pengikatan metadata lengkap (M4A-01 s/d M4A-06).
     */
    public function exportLetter(User $user, Letter $letter, string $format, ?string $idempotencyKey = null): DocumentExport
    {
        $format = strtoupper(trim($format));
        if (! in_array($format, ['PDF', 'DOCX'], true)) {
            throw new InvalidArgumentException("Format ekspor tidak didukung: {$format}. Gunakan PDF atau DOCX.");
        }

        // 1. Verifikasi Otorisasi & Tenancy (M4A-01, M4A-08)
        if ($letter->organization_id !== $user->organization_id) {
            throw new DomainException('Akses ditolak: Surat bukan milik organisasi pengguna.');
        }

        // 2. Proteksi Dokumen Berklasifikasi CONFIDENTIAL / RAHASIA (M4A-09)
        if (in_array($letter->classification, ['CONFIDENTIAL', 'RAHASIA'], true)) {
            $isAuthor = $letter->author_id === $user->id;
            $isReviewerOrAdmin = $user->isSystemAdmin() || $user->hasRole('REVIEWER') || $user->hasRole('PENGURUS_YAYASAN');
            if (! $isAuthor && ! $isReviewerOrAdmin) {
                throw new DomainException('Akses ditolak: Dokumen berklasifikasi RAHASIA (CONFIDENTIAL) hanya dapat diakses oleh konseptor dan penelaah.');
            }
        }

        // 3. Ambil Revisi Naskah Sasaran
        $revision = $letter->latestRevision;
        if (! $revision) {
            throw new DomainException('Surat tidak memiliki data revisi naskah untuk diekspor.');
        }

        // 4. Periksa Idempotency Key (M4A-12)
        $idempotencyKey = $idempotencyKey ?: 'exp_'.$letter->id.'_'.$revision->id.'_'.strtolower($format).'_'.Str::random(12);

        $existingExport = DocumentExport::where('idempotency_key', $idempotencyKey)->first();
        if ($existingExport && $existingExport->isReady()) {
            return $existingExport;
        }

        // 5. Buat Record Staging Berstatus PENDING (M4A-13)
        $export = DocumentExport::create([
            'organization_id' => $letter->organization_id,
            'letter_id' => $letter->id,
            'revision_id' => $revision->id,
            'revision_version' => $revision->version,
            'content_hash' => hash('sha256', json_encode($revision->content)),
            'format' => $format,
            'status' => 'PENDING',
            'idempotency_key' => $idempotencyKey,
            'exported_by' => $user->id,
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'p4i_export_').'.'.strtolower($format);

        try {
            // 6. Siapkan Konteks Dokumen Bersama (Unified Source Data)
            $context = $this->prepareDocumentContext($letter, $revision, $user);

            // 7. Eksekusi Generator Sesuai Format
            if ($format === 'PDF') {
                $this->renderPdf($context, $tempPath);
                $mimeType = 'application/pdf';
            } else {
                $this->renderDocx($context, $tempPath);
                $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }

            if (! file_exists($tempPath) || filesize($tempPath) === 0) {
                throw new DomainException('Gagal menghasilkan berkas luaran dokumen.');
            }

            // 8. Hitung Checksum SHA-256 dan Ukuran Fisik
            $fileHash = hash_file('sha256', $tempPath);
            $fileSize = filesize($tempPath);

            // 9. Jalur Penyimpanan Privat Terisolasi (M4A-07)
            $year = Carbon::now()->format('Y');
            $storageRelativePath = "{$letter->organization_id}/{$year}/{$format}/{$export->id}.".strtolower($format);

            // Simpan ke private disk
            $fileContents = file_get_contents($tempPath);
            Storage::disk(self::DISK)->put($storageRelativePath, $fileContents);

            // Hapus berkas sementara
            @unlink($tempPath);

            // 10. Perbarui Status Menjadi READY Secara Atomik
            $export->update([
                'file_path' => $storageRelativePath,
                'file_hash' => $fileHash,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'status' => 'READY',
                'error_message' => null,
            ]);

            // Catat Log Audit Ekspor Dokumen
            AuditEvent::create([
                'organization_id' => $letter->organization_id,
                'actor_id' => $user->id,
                'event_type' => 'DOCUMENT_EXPORTED',
                'entity_type' => 'document_exports',
                'entity_id' => $export->id,
                'event_metadata' => [
                    'letter_id' => $letter->id,
                    'revision_version' => $revision->version,
                    'format' => $format,
                    'sha256' => $fileHash,
                    'file_size' => $fileSize,
                ],
                'occurred_at' => now(),
            ]);

            return $export;
        } catch (\Throwable $e) {
            // Pembersihan berkas jika terjadi kegagalan (Pencegahan Berkas Yatim - M4A-13)
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            $export->update([
                'status' => 'ERROR',
                'error_message' => $e->getMessage(),
            ]);

            AuditEvent::create([
                'organization_id' => $letter->organization_id,
                'actor_id' => $user->id,
                'event_type' => 'DOCUMENT_EXPORT_FAILED',
                'entity_type' => 'document_exports',
                'entity_id' => $export->id,
                'event_metadata' => [
                    'letter_id' => $letter->id,
                    'format' => $format,
                    'error' => $e->getMessage(),
                ],
                'occurred_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Menyiapkan konteks naskah seragam untuk PDF dan DOCX (Unified Source Data) (M4A-04, M4A-05).
     */
    private function prepareDocumentContext(Letter $letter, LetterRevision $revision, User $exporter): array
    {
        $org = $letter->organization;
        $content = is_array($revision->content) ? $revision->content : (json_decode((string) $revision->content, true) ?: []);

        $bodyParagraphs = [];
        if (isset($content['body']) && is_array($content['body'])) {
            foreach ($content['body'] as $para) {
                $bodyParagraphs[] = trim((string) $para);
            }
        } elseif (isset($content['body']) && is_string($content['body'])) {
            $bodyParagraphs = array_filter(explode("\n", $content['body']));
        } else {
            $bodyParagraphs = ['(Isi surat belum dikonfigurasikan)'];
        }

        // Sanitasi teks agar aman dari eksekusi kode (M4A-06)
        $cleanParagraphs = array_map(function ($p) {
            $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', (string) $p);

            return htmlspecialchars(trim(strip_tags((string) $clean)), ENT_QUOTES, 'UTF-8');
        }, $bodyParagraphs);

        $plainParagraphs = array_map(function ($p) {
            $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', (string) $p);

            return trim(strip_tags((string) $clean));
        }, $bodyParagraphs);

        $dateFormatted = Carbon::parse($letter->planned_date ?: now())->locale('id')->translatedFormat('d F Y');

        // Nomor Surat: Tetap NULL / Pratinjau Draf Simulasi (M4A-05)
        $draftNumber = '[SIMULASI] DRAF/P4I/'.Carbon::now()->format('Y').'/REV-'.$revision->version;

        return [
            'org_name' => $org->legal_name ?? 'Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia (Yayasan P4I)',
            'org_acronym' => $org->acronym ?? 'Yayasan P4I',
            'org_address' => $org->address_placeholder ?? 'Gedung P4I Lantai 3, Jl. Pembangunan Nasional, Jakarta',
            'letter_number' => $draftNumber,
            'official_number_status' => 'DINONAKTIFKAN (STRICTLY NULL)',
            'classification' => $letter->classification,
            'date_formatted' => $dateFormatted,
            'recipient_name' => htmlspecialchars($letter->recipient_name, ENT_QUOTES, 'UTF-8'),
            'subject' => htmlspecialchars($letter->subject, ENT_QUOTES, 'UTF-8'),
            'body_paragraphs' => $cleanParagraphs,
            'plain_body_paragraphs' => $plainParagraphs,
            'revision_version' => $revision->version,
            'exported_by_name' => $exporter->display_name,
            'exported_at' => Carbon::now()->format('d/m/Y H:i:s').' WIB',
            'watermark_text' => 'DRAF — BELUM RESMI — TIDAK BERLAKU SEBAGAI SURAT KELUAR',
            'disclaimer_text' => 'Dokumen ini berstatus DRAF SIMULASI dan belum diterbitkan secara resmi oleh Yayasan P4I. Segala bentuk penomoran resmi, tanda tangan, dan stempel dinonaktifkan.',
        ];
    }

    /**
     * Render dokumen PDF menggunakan Dompdf terisolasi dengan watermark di setiap halaman (M4A-03, M4A-06).
     */
    private function renderPdf(array $ctx, string $outputPath): void
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false); // Keamanan: Tolak SSRF / remote resource
        $options->set('isPhpEnabled', false);    // Keamanan: Tolak embedded PHP execution
        $options->set('chroot', base_path());    // Batasi akses file lokal
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);

        // Susun HTML berwatermark permanen
        $html = $this->buildPdfHtml($ctx);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($outputPath, $dompdf->output());
    }

    /**
     * Membangun HTML untuk PDF dengan watermark pada setiap halaman.
     */
    private function buildPdfHtml(array $ctx): string
    {
        $paragraphsHtml = '';
        foreach ($ctx['body_paragraphs'] as $p) {
            $paragraphsHtml .= '<p style="margin-bottom: 12px; text-align: justify; line-height: 1.6;">'.$p.'</p>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>DRAF SURAT P4I</title>
    <style>
        @page {
            margin: 25mm 20mm 25mm 20mm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            position: relative;
        }
        .watermark {
            position: fixed;
            top: 40%;
            left: -5%;
            width: 110%;
            text-align: center;
            opacity: 0.12;
            transform: rotate(-35deg);
            font-size: 30pt;
            font-weight: bold;
            color: #b91c1c;
            z-index: -1000;
            text-transform: uppercase;
            line-height: 1.3;
        }
        .kop-table {
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }
        .kop-logo {
            width: 70px;
            font-weight: bold;
            font-size: 24pt;
            color: #1e3a8a;
            vertical-align: middle;
        }
        .kop-text {
            text-align: center;
            vertical-align: middle;
        }
        .kop-title {
            font-size: 12pt;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            margin: 0;
        }
        .kop-sub {
            font-size: 8.5pt;
            color: #4b5563;
            margin-top: 2px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 16px;
            font-size: 10.5pt;
        }
        .meta-table td {
            vertical-align: top;
            padding: 2px 0;
        }
        .recipient-box {
            margin-bottom: 18px;
            font-size: 10.5pt;
        }
        .content-box {
            margin-bottom: 25px;
        }
        .signatory-box {
            width: 100%;
            margin-top: 30px;
        }
        .disclaimer-box {
            margin-top: 30px;
            border: 1px dashed #dc2626;
            background-color: #fef2f2;
            padding: 8px 12px;
            font-size: 8pt;
            color: #991b1b;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Watermark Permanen di Setiap Halaman (M4A-03) -->
    <div class="watermark">
        DRAF — BELUM RESMI<br>
        <span style="font-size: 18pt;">TIDAK BERLAKU SEBAGAI SURAT RESMI</span>
    </div>

    <!-- Kop Naskah Dinas Yayasan P4I -->
    <table class="kop-table">
        <tr>
            <td class="kop-logo">P4I</td>
            <td class="kop-text">
                <div class="kop-title">{$ctx['org_name']}</div>
                <div class="kop-sub">{$ctx['org_address']}</div>
            </td>
        </tr>
    </table>

    <!-- Metadata Surat Draf -->
    <table class="meta-table">
        <tr>
            <td style="width: 15%;">Nomor</td>
            <td style="width: 2%;">:</td>
            <td style="width: 48%; font-weight: bold; color: #b91c1c;">{$ctx['letter_number']}</td>
            <td style="width: 35%; text-align: right;">Jakarta, {$ctx['date_formatted']}</td>
        </tr>
        <tr>
            <td>Sifat</td>
            <td>:</td>
            <td>{$ctx['classification']}</td>
            <td></td>
        </tr>
        <tr>
            <td>Perihal</td>
            <td>:</td>
            <td><strong>{$ctx['subject']}</strong></td>
            <td></td>
        </tr>
    </table>

    <!-- Tujuan / Penerima -->
    <div class="recipient-box">
        Kepada Yth.<br>
        <strong>{$ctx['recipient_name']}</strong><br>
        di Tempat
    </div>

    <!-- Isi Naskah Dinas -->
    <div class="content-box">
        {$paragraphsHtml}
    </div>

    <!-- Blok Penandatangan (Dengan Penanda Jelas Belum Sah) (M4A-05) -->
    <table class="signatory-box">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%; text-align: center;">
                <div>Dewan Pengurus Yayasan P4I</div>
                <div style="height: 50px; line-height: 50px; color: #9ca3af; font-style: italic; font-size: 9pt;">
                    [TANDA TANGAN BELUM DITERBITKAN]
                </div>
                <div style="font-weight: bold; text-decoration: underline;">Drs. H. Mulyadi, M.M.</div>
                <div style="font-size: 9pt; color: #4b5563;">Ketua Yayasan</div>
            </td>
        </tr>
    </table>

    <!-- Penafian Hukum & Metadata Ekspor (M4A-05, M4A-07) -->
    <div class="disclaimer-box">
        <strong>PERHATIAN KEARSIPAN:</strong> {$ctx['disclaimer_text']}<br>
        Diekspor oleh: {$ctx['exported_by_name']} | Waktu: {$ctx['exported_at']} | Revisi Ke-{$ctx['revision_version']}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render dokumen DOCX menggunakan PhpWord terisolasi dengan watermark header dan escaping (M4A-02, M4A-06).
     */
    private function renderDocx(array $ctx, string $outputPath): void
    {
        $phpWord = new PhpWord;

        // Atur font default
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        // 1. Header dengan Watermark Draf Tegas (M4A-02, M4A-03)
        $header = $section->addHeader();
        $header->addText(
            $ctx['watermark_text'],
            ['bold' => true, 'color' => 'B91C1C', 'size' => 9],
            ['alignment' => Jc::CENTER]
        );

        // 2. Kop Surat Yayasan P4I
        $section->addText(
            $ctx['org_name'],
            ['bold' => true, 'size' => 12, 'color' => '111827'],
            ['alignment' => Jc::CENTER]
        );
        $section->addText(
            $ctx['org_address'],
            ['size' => 9, 'color' => '4B5563', 'italic' => true],
            ['alignment' => Jc::CENTER]
        );
        $section->addLine(['weight' => 2, 'width' => 450, 'height' => 0, 'color' => '000000']);
        $section->addTextBreak(1);

        // 3. Metadata Surat
        $section->addText("Nomor   : {$ctx['letter_number']}", ['bold' => true, 'color' => 'B91C1C']);
        $section->addText("Sifat   : {$ctx['classification']}");
        $section->addText("Perihal : {$ctx['subject']}", ['bold' => true]);
        $section->addText("Tanggal : Jakarta, {$ctx['date_formatted']}");
        $section->addTextBreak(1);

        // 4. Tujuan Surat
        $section->addText('Kepada Yth.');
        $section->addText($ctx['recipient_name'], ['bold' => true]);
        $section->addText('di Tempat');
        $section->addTextBreak(1);

        // 5. Isi Naskah Dinas
        foreach ($ctx['plain_body_paragraphs'] as $p) {
            $section->addText($p, [], ['alignment' => Jc::BOTH, 'spaceAfter' => 180]);
        }

        $section->addTextBreak(1);

        // 6. Penandatangan (Dengan Penanda Jelas Belum Sah - M4A-05)
        $section->addText('Dewan Pengurus Yayasan P4I', [], ['alignment' => Jc::RIGHT]);
        $section->addText('[TANDA TANGAN BELUM DITERBITKAN]', ['italic' => true, 'color' => '9CA3AF', 'size' => 9], ['alignment' => Jc::RIGHT]);
        $section->addText('Drs. H. Mulyadi, M.M.', ['bold' => true, 'underline' => 'single'], ['alignment' => Jc::RIGHT]);
        $section->addText('Ketua Yayasan', [], ['alignment' => Jc::RIGHT]);

        $section->addTextBreak(2);

        // 7. Footer Penafian Hukum
        $footer = $section->addFooter();
        $footer->addText(
            $ctx['disclaimer_text'],
            ['size' => 8, 'italic' => true, 'color' => 'B91C1C'],
            ['alignment' => Jc::CENTER]
        );

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($outputPath);
    }

    /**
     * Memproses pengunduhan dokumen arsip privat secara aman dan terverifikasi integritas (M4A-08, M4A-10, M4A-11).
     */
    public function downloadExport(User $user, DocumentExport $export): StreamedResponse
    {
        // 1. Verifikasi Status Berkas
        if (! $export->isReady()) {
            throw new DomainException("Dokumen ekspor berstatus {$export->status} dan belum siap diunduh.");
        }

        // 2. Verifikasi Tenancy Organisasi (M4A-08)
        if ($export->organization_id !== $user->organization_id) {
            throw new DomainException('Akses ditolak: Dokumen bukan milik organisasi pengguna.');
        }

        // 3. Verifikasi Izin Klasifikasi CONFIDENTIAL / RAHASIA (M4A-09)
        $letter = $export->letter;
        if (in_array($letter->classification, ['CONFIDENTIAL', 'RAHASIA'], true)) {
            $isAuthor = $letter->author_id === $user->id;
            $isReviewerOrAdmin = $user->isSystemAdmin() || $user->hasRole('REVIEWER') || $user->hasRole('PENGURUS_YAYASAN');
            if (! $isAuthor && ! $isReviewerOrAdmin) {
                throw new DomainException('Akses ditolak: Dokumen rahasia hanya dapat diunduh oleh konseptor atau penelaah naskah.');
            }
        }

        // 4. Verifikasi Keberadaan Berkas Fisik & Anti Path Traversal (M4A-10)
        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($export->file_path)) {
            throw new DomainException('Berkas arsip fisik tidak ditemukan di penyimpanan privat.');
        }

        // 5. Verifikasi Integritas Checksum SHA-256 (M4A-11)
        $actualSha256 = hash_file('sha256', $disk->path($export->file_path));
        if ($actualSha256 !== $export->file_hash) {
            throw new DomainException('Integritas berkas gagal diverifikasi: Hash SHA-256 tidak cocok dengan metadata sistem.');
        }

        // 6. Mutasi Metadata Unduh
        $export->increment('download_count');
        $export->update(['last_downloaded_at' => now()]);

        // 7. Log Audit Unduh
        AuditEvent::create([
            'organization_id' => $user->organization_id,
            'actor_id' => $user->id,
            'event_type' => 'DOCUMENT_DOWNLOADED',
            'entity_type' => 'document_exports',
            'entity_id' => $export->id,
            'event_metadata' => [
                'letter_id' => $letter->id,
                'format' => $export->format,
                'revision_version' => $export->revision_version,
                'sha256' => $export->file_hash,
            ],
            'occurred_at' => now(),
        ]);

        $safeDownloadFilename = 'DRAF_'.Str::slug($letter->subject ?: 'surat').'_REV-'.$export->revision_version.'.'.strtolower($export->format);

        return response()->streamDownload(function () use ($disk, $export) {
            $stream = $disk->readStream($export->file_path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $safeDownloadFilename, [
            'Content-Type' => $export->mime_type,
            'Content-Length' => $export->file_size,
        ]);
    }

    /**
     * Memverifikasi integritas seluruh arsip fisik terhadap data tersimpan di basis data (M4A-11, M4A-14).
     */
    public function verifyArchiveIntegrity(User $user): array
    {
        $exports = DocumentExport::where('organization_id', $user->organization_id)
            ->where('status', 'READY')
            ->get();

        $disk = Storage::disk(self::DISK);
        $totalFiles = $exports->count();
        $validFiles = 0;
        $corruptedFiles = 0;
        $missingFiles = 0;
        $totalBytes = 0;

        foreach ($exports as $exp) {
            if (! $disk->exists($exp->file_path)) {
                $missingFiles++;

                continue;
            }

            $currentHash = hash_file('sha256', $disk->path($exp->file_path));
            if ($currentHash === $exp->file_hash) {
                $validFiles++;
                $totalBytes += $exp->file_size;
            } else {
                $corruptedFiles++;
            }
        }

        return [
            'total_files' => $totalFiles,
            'valid_files' => $validFiles,
            'corrupted_files' => $corruptedFiles,
            'missing_files' => $missingFiles,
            'total_bytes' => $totalBytes,
            'all_intact' => ($totalFiles > 0 && $validFiles === $totalFiles),
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
