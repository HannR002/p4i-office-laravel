<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\LegacyRegisterBatch;
use App\Models\LegacyRegisterRow;
use App\Models\ReconciliationAuditLog;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ZipArchive;

class LegacyRegisterService
{
    /**
     * Memproses impor berkas CSV atau XLSX register historis ke tabel staging dengan deteksi anomali komprehensif (BR-02, BR-06, BR-07).
     */
    public function importFile(User $user, UploadedFile $file, ?string $notes = null): LegacyRegisterBatch
    {
        // 1. Validasi Keamanan Berkas (BR-02, BR-17)
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'xlsx'])) {
            throw new InvalidArgumentException('Berkas harus berekstensi .csv, .txt, atau .xlsx');
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Ukuran berkas melebihi batas maksimum 5 MB');
        }

        $filename = $file->getClientOriginalName();
        $fileHash = hash_file('sha256', $file->getRealPath());

        // 2. Pembacaan Format (CSV/TXT vs XLSX)
        if ($extension === 'xlsx') {
            $rawRows = $this->parseXlsx($file->getRealPath());
        } else {
            $rawRows = $this->parseCsv($file->getRealPath());
        }

        if (empty($rawRows)) {
            throw new InvalidArgumentException('Berkas kosong atau tidak memiliki baris tajuk (header)');
        }

        // Header mapping
        $header = array_shift($rawRows);
        $header = array_map(function ($col) {
            return strtolower(trim((string) $col));
        }, $header);

        $numIdx = array_search('nomor_surat', $header) !== false ? array_search('nomor_surat', $header) : 0;
        $dateIdx = array_search('tanggal', $header) !== false ? array_search('tanggal', $header) : 1;
        $typeIdx = array_search('jenis', $header) !== false ? array_search('jenis', $header) : 2;
        $recipIdx = array_search('penerima', $header) !== false ? array_search('penerima', $header) : 3;
        $subjIdx = array_search('perihal', $header) !== false ? array_search('perihal', $header) : 4;
        $locIdx = array_search('lokasi_berkas', $header) !== false ? array_search('lokasi_berkas', $header) : 5;

        $rowsData = [];
        $rowIndex = 1;
        $seenNumbersInBatch = [];

        foreach ($rawRows as $row) {
            if (empty(array_filter($row))) {
                continue; // Lewati baris kosong
            }

            $rawNumber = isset($row[$numIdx]) ? trim((string) $row[$numIdx]) : '';
            $dateStr = isset($row[$dateIdx]) ? trim((string) $row[$dateIdx]) : '';
            $typeCode = isset($row[$typeIdx]) ? trim((string) $row[$typeIdx]) : '';
            $recipient = isset($row[$recipIdx]) ? trim((string) $row[$recipIdx]) : '';
            $subject = isset($row[$subjIdx]) ? trim((string) $row[$subjIdx]) : '';
            $sourceLoc = isset($row[$locIdx]) ? trim((string) $row[$locIdx]) : null;

            // Sanitasi CSV / Spreadsheet Formula Injection (menghindari =, +, -, @)
            $rawNumber = ltrim($rawNumber, "=+-@\t\r");
            $recipient = ltrim($recipient, "=+-@\t\r");
            $subject = ltrim($subject, "=+-@\t\r");
            if ($sourceLoc) {
                $sourceLoc = ltrim($sourceLoc, "=+-@\t\r");
            }

            // Validasi Anomali dan Konflik (BR-06, BR-07, BR-09)
            $isValid = true;
            $conflictType = null;
            $conflictDetails = null;
            $docDate = null;
            $provenanceYear = null;
            $provenanceSeries = null;

            // Anomali 1: Nomor Kosong
            if ($rawNumber === '') {
                $isValid = false;
                $conflictType = 'EMPTY_NUMBER';
                $conflictDetails = 'Nomor surat pada baris fisik ini kosong atau tidak tercatat pada register.';
            }
            // Anomali 2: Duplikasi dalam batch atau database
            elseif (isset($seenNumbersInBatch[$rawNumber])) {
                $isValid = false;
                $conflictType = 'DUPLICATE_NUMBER';
                $conflictDetails = "Nomor '{$rawNumber}' terduplikasi dalam batch impor yang sama (baris {$seenNumbersInBatch[$rawNumber]}).";
            } else {
                $existingInDb = LegacyRegisterRow::where('raw_number', $rawNumber)->exists();
                if ($existingInDb) {
                    $isValid = false;
                    $conflictType = 'DUPLICATE_NUMBER';
                    $conflictDetails = "Nomor '{$rawNumber}' telah ada pada arsip register staging sebelumnya.";
                }
            }

            // Anomali 3: Format Anomali
            if ($isValid && ! preg_match('/^[0-9A-Za-z\/\-\.\s]+$/', $rawNumber)) {
                $isValid = false;
                $conflictType = 'FORMAT_ANOMALY';
                $conflictDetails = "Nomor '{$rawNumber}' mengandung karakter ilegal atau struktur format janggal.";
            }

            // Anomali 4: Validasi Tanggal & Ekstraksi Provenance
            if ($dateStr !== '') {
                try {
                    $parsedDate = Carbon::parse($dateStr);
                    $docDate = $parsedDate->format('Y-m-d');
                    $provenanceYear = (int) $parsedDate->format('Y');

                    if ($parsedDate->isFuture()) {
                        $isValid = false;
                        $conflictType = 'DATE_INCONSISTENCY';
                        $conflictDetails = "Tanggal surat '{$dateStr}' berada di masa depan.";
                    }
                } catch (\Exception $e) {
                    $isValid = false;
                    $conflictType = 'DATE_INCONSISTENCY';
                    $conflictDetails = "Format tanggal '{$dateStr}' tidak dapat dikenali.";
                }
            } else {
                if ($isValid) {
                    $isValid = false;
                    $conflictType = 'MISSING_REQUIRED_FIELDS';
                    $conflictDetails = 'Tanggal surat wajib diisi untuk rekonsiliasi arsip.';
                }
            }

            if ($rawNumber !== '') {
                $seenNumbersInBatch[$rawNumber] = $rowIndex;

                // Coba kenali seri naskah dari nomor asli (BR-06)
                if (preg_match('/\/([A-Za-z\-]+)\//', $rawNumber, $m)) {
                    $provenanceSeries = strtoupper($m[1]);
                }
            }

            $rowsData[] = [
                'row_index' => $rowIndex++,
                'raw_number' => $rawNumber,
                'normalized_number' => $rawNumber ? strtoupper(preg_replace('/\s+/', '', $rawNumber)) : null,
                'document_date' => $docDate,
                'letter_type_code' => $typeCode ?: null,
                'recipient' => $recipient ?: null,
                'subject' => $subject ?: null,
                'source_location' => $sourceLoc ?: 'Buku Register Fisik Sekretariat',
                'provenance_year' => $provenanceYear,
                'provenance_series' => $provenanceSeries,
                'is_valid' => $isValid,
                'conflict_type' => $conflictType,
                'conflict_details' => $conflictDetails,
                'verification_status' => $isValid ? 'UNVERIFIED' : 'CONFLICT', // BR-07
                'reconciliation_action' => $isValid ? null : 'FLAGGED_FOR_BOARD',
            ];
        }

        $totalRows = count($rowsData);
        $validRows = count(array_filter($rowsData, fn ($r) => $r['is_valid']));
        $conflictRows = $totalRows - $validRows;
        $batchStatus = $conflictRows > 0 ? 'HAS_CONFLICTS' : 'STAGED';

        return DB::transaction(function () use (
            $user,
            $filename,
            $fileHash,
            $totalRows,
            $validRows,
            $conflictRows,
            $batchStatus,
            $notes,
            $rowsData
        ) {
            $batch = LegacyRegisterBatch::create([
                'organization_id' => $user->organization_id,
                'source_filename' => $filename,
                'source_hash' => $fileHash,
                'total_rows' => $totalRows,
                'valid_rows' => $validRows,
                'conflict_rows' => $conflictRows,
                'status' => $batchStatus,
                'is_sample' => false,
                'imported_by' => $user->id,
                'notes' => $notes,
            ]);

            foreach ($rowsData as $row) {
                $row['batch_id'] = $batch->id;
                LegacyRegisterRow::create($row);
            }

            AuditEvent::create([
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'event_type' => 'LEGACY_REGISTER_BATCH_IMPORTED',
                'entity_type' => 'legacy_register_batches',
                'entity_id' => $batch->id,
                'payload' => [
                    'filename' => $filename,
                    'hash' => $fileHash,
                    'total_rows' => $totalRows,
                    'valid_rows' => $validRows,
                    'conflict_rows' => $conflictRows,
                    'status' => $batchStatus,
                ],
            ]);

            return $batch;
        });
    }

    /**
     * Backward-compatible alias untuk importCsv.
     */
    public function importCsv(User $user, UploadedFile $file, ?string $notes = null): LegacyRegisterBatch
    {
        return $this->importFile($user, $file, $notes);
    }

    /**
     * Rekonsiliasi individual baris register lama oleh Tim Arsiparis / Reviewer resmi (BR-07, BR-08, BR-09, SC-07, SC-08).
     * Menerapkan prinsip Segregation of Duties (SoD) dan append-only audit trail.
     */
    public function reconcileRow(
        User $user,
        LegacyRegisterRow $row,
        string $status,
        string $action,
        string $notes,
        ?string $boardRef = null
    ): LegacyRegisterRow {
        $allowedStatuses = ['VERIFIED', 'CONFLICT', 'UNVERIFIED'];
        if (! in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Status rekonsiliasi tidak valid: {$status}");
        }

        $allowedActions = ['RETAIN_AS_IS', 'AMEND_OFFICIAL_RECORD', 'VOID_ENTRY', 'FLAGGED_FOR_BOARD'];
        if (! in_array($action, $allowedActions, true)) {
            throw new InvalidArgumentException("Tindakan rekonsiliasi tidak valid: {$action}");
        }

        // Segregation of Duties 1: Larangan Self-Approval (SC-08)
        // Pengusul perubahan dilarang mengesahkan/memverifikasi usulannya sendiri
        if ($row->proposed_by && $row->proposed_by === $user->id && in_array($status, ['VERIFIED', 'CONFLICT'])) {
            throw new DomainException('Prinsip Segregation of Duties: Pengusul perubahan register dilarang memverifikasi keputusannya sendiri.');
        }

        // Segregation of Duties 2: Pengunggah batch dilarang memverifikasi baris miliknya sendiri tanpa mandat pengurus
        if ($row->batch && $row->batch->imported_by === $user->id && ! $user->hasRole('PENGURUS_YAYASAN') && ! $user->hasRole('REVIEWER')) {
            throw new DomainException('Prinsip Segregation of Duties: Pengunggah berkas register dilarang memvalidasi baris arsip miliknya sendiri.');
        }

        // Segregation of Duties 3: Administrator teknis dilarang mengesahkan arsip resmi tanpa SK/Berita Acara Pengurus (SC-08)
        if ($user->isSystemAdmin() && ! $user->hasRole('PENGURUS_YAYASAN')) {
            if (in_array($action, ['RETAIN_AS_IS', 'AMEND_OFFICIAL_RECORD', 'VOID_ENTRY']) && empty($boardRef)) {
                throw new DomainException('Administrator teknis tidak memiliki kewenangan yuridis menetapkan validitas arsip resmi tanpa Nomor SK/Berita Acara Dewan Pengurus.');
            }
        }

        $prevStatus = $row->verification_status;
        $proposerId = $row->proposed_by ?: $row->batch?->imported_by;

        return DB::transaction(function () use ($user, $row, $status, $action, $notes, $boardRef, $prevStatus, $proposerId) {
            // Append-only: raw_number, source_location, provenance asli tidak pernah ditimpa
            $row->verification_status = $status;
            $row->reconciliation_action = $action;
            $row->reconciliation_notes = $notes;
            $row->board_resolution_ref = $boardRef;
            $row->verified_by = $user->id;
            $row->verified_at = now();
            $row->save();

            // Catat log rekonsiliasi immutable dengan informasi lengkap pengusul dan pemeriksa (SC-07, SC-08)
            ReconciliationAuditLog::create([
                'row_id' => $row->id,
                'user_id' => $user->id,
                'proposer_id' => $proposerId,
                'verifier_id' => $user->id,
                'previous_status' => $prevStatus,
                'new_status' => $status,
                'action_taken' => $action,
                'board_resolution_ref' => $boardRef,
                'rationale' => $notes,
                'notes' => $notes,
            ]);

            // Sinkronkan status batch
            $batch = $row->batch;
            if ($batch) {
                $hasConflict = LegacyRegisterRow::where('batch_id', $batch->id)
                    ->where('verification_status', 'CONFLICT')
                    ->exists();

                $allVerified = ! LegacyRegisterRow::where('batch_id', $batch->id)
                    ->where('verification_status', '!=', 'VERIFIED')
                    ->exists();

                $batch->status = $allVerified ? 'VERIFIED' : ($hasConflict ? 'HAS_CONFLICTS' : 'STAGED');
                $batch->save();
            }

            return $row;
        });
    }

    /**
     * Memverifikasi batch oleh reviewer arsip resmi dengan penegakan SoD (SC-08).
     */
    public function verifyBatch(User $user, LegacyRegisterBatch $batch, bool $approved, string $notes): void
    {
        if ($batch->organization_id !== $user->organization_id) {
            throw new DomainException('Batch register bukan milik organisasi pengguna.');
        }

        // Segregation of Duties: Pengunggah batch dilarang memverifikasi batch-nya sendiri tanpa mandat Pengurus
        if ($batch->imported_by === $user->id && ! $user->hasRole('PENGURUS_YAYASAN')) {
            throw new DomainException('Prinsip Segregation of Duties: Pengunggah batch register dilarang memverifikasi batch miliknya sendiri.');
        }

        DB::transaction(function () use ($user, $batch, $approved, $notes) {
            $status = $approved ? 'VERIFIED' : 'REJECTED';
            $batch->status = $status;
            $batch->notes = ($batch->notes ? $batch->notes."\n" : '')."Review [{$user->display_name}]: {$notes}";
            $batch->save();

            LegacyRegisterRow::where('batch_id', $batch->id)
                ->where('is_valid', true)
                ->update([
                    'verification_status' => $status,
                    'verified_by' => $user->id,
                    'verified_at' => now(),
                ]);

            AuditEvent::create([
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'event_type' => 'LEGACY_REGISTER_BATCH_VERIFIED',
                'entity_type' => 'legacy_register_batches',
                'entity_id' => $batch->id,
                'payload' => [
                    'status' => $status,
                    'notes' => $notes,
                ],
            ]);
        });
    }

    /**
     * Pembaca berkas CSV secara aman dengan batas baris, kolom, dan panjang karakter (SC-04, SC-06).
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new DomainException('Gagal membuka berkas CSV register');
        }

        $rows = [];
        $rowCount = 0;

        while (($data = fgetcsv($handle, 4096, ',')) !== false) {
            $rowCount++;
            if ($rowCount > 5000) {
                fclose($handle);
                throw new InvalidArgumentException('Jumlah baris dalam berkas melebihi batas maksimum 5.000 baris.');
            }

            if (count($data) > 50) {
                fclose($handle);
                throw new InvalidArgumentException('Jumlah kolom dalam berkas melebihi batas maksimum 50 kolom.');
            }

            foreach ($data as $cell) {
                if (strlen((string) $cell) > 1000) {
                    fclose($handle);
                    throw new InvalidArgumentException('Panjang teks dalam sel melebihi batas maksimum 1.000 karakter.');
                }
            }

            $rows[] = $data;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Parser XLSX native yang diperkuat secara komprehensif (CR-01, SC-02, SC-03, SC-04, SC-05).
     * Mencegah entity expansion (tanpa LIBXML_NOENT), DTD/XXE, ZIP Bomb, Path Traversal, Macro, dan External Links.
     */
    private function parseXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Berkas XLSX rusak atau tidak memiliki struktur zip yang valid.');
        }

        // 1. Batasi jumlah total entri dalam arsip ZIP (SC-04)
        $numFiles = $zip->numFiles;
        if ($numFiles > 100) {
            $zip->close();
            throw new InvalidArgumentException('Jumlah berkas internal ZIP melebihi batas keamanan maksimum (100 berkas).');
        }

        // 2. Audit path traversal, ZIP bomb, ukuran dekompresi, dan berkas terlarang (SC-03, SC-04, SC-05)
        $totalUncompressedSize = 0;
        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (! $stat) {
                continue;
            }

            $name = $stat['name'];

            // 2.1 Audit Path Traversal (SC-04)
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\') || str_contains($name, ':')) {
                $zip->close();
                throw new InvalidArgumentException("Nama entri berkas terindikasi path traversal terlarang: {$name}");
            }

            // 2.2 Penolakan Macro, Script, dan Komponen Terlarang (SC-05)
            $lowerName = strtolower($name);
            if (
                str_ends_with($lowerName, '.bin') ||
                str_ends_with($lowerName, '.exe') ||
                str_ends_with($lowerName, '.vbs') ||
                str_ends_with($lowerName, '.bat') ||
                str_ends_with($lowerName, '.cmd') ||
                str_ends_with($lowerName, '.sh') ||
                str_contains($lowerName, 'vbaproject') ||
                str_contains($lowerName, 'customxml')
            ) {
                $zip->close();
                throw new InvalidArgumentException('Berkas XLSX memuat komponen terlarang (Macro/VBA/Script).');
            }

            // 2.3 Penolakan Pranala Eksternal (External Links / Relationships) (SC-05)
            if (str_contains($lowerName, 'externallink')) {
                $zip->close();
                throw new InvalidArgumentException('Berkas XLSX memuat pranala luar (external links) yang dilarang.');
            }

            // 2.4 Batas Ukuran Dekompresi per Berkas (Maksimal 5 MB) (SC-04)
            $size = $stat['size'];
            if ($size > 5 * 1024 * 1024) {
                $zip->close();
                throw new InvalidArgumentException("Ukuran dekompresi berkas {$name} melebihi batas keamanan 5 MB.");
            }

            // 2.5 Deteksi Rasio Kompresi Ekstrem / ZIP Bomb (SC-04)
            $compSize = $stat['comp_size'];
            if ($compSize > 0 && ($size / $compSize) > 100 && $size > 1024 * 1024) {
                $zip->close();
                throw new InvalidArgumentException('Rasio kompresi berkas tidak wajar (terindikasi serangan ZIP Bomb).');
            }

            $totalUncompressedSize += $size;
        }

        // 2.6 Batas Kumulatif Dekompresi Seluruh Entri (Maksimal 10 MB) (SC-04)
        if ($totalUncompressedSize > 10 * 1024 * 1024) {
            $zip->close();
            throw new InvalidArgumentException('Total ukuran hasil ekstraksi melebihi batas keamanan kumulatif 10 MB (ZIP Bomb protection).');
        }

        // 3. Ekstraksi Shared Strings dengan Proteksi DTD & XXE (SC-02, SC-03)
        $sharedStrings = [];
        $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($stringsXml !== false) {
            $sharedStrings = $this->parseSharedStringsXml($stringsXml);
        }

        // 4. Ekstraksi Lembar Kerja Utama (sheet1.xml)
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new InvalidArgumentException('Lembar kerja utama (sheet1.xml) tidak ditemukan dalam berkas XLSX.');
        }

        return $this->parseWorksheetXml($sheetXml, $sharedStrings);
    }

    /**
     * Memparsing XML Shared Strings dengan validasi ketat anti DTD/XXE/Entity Substitution (CR-01, SC-02, SC-03).
     */
    private function parseSharedStringsXml(string $xmlContent): array
    {
        $this->assertSafeXmlContent($xmlContent);

        libxml_use_internal_errors(true);
        // HANYA LIBXML_NONET (tanpa LIBXML_NOENT, tanpa DTDLOAD, tanpa DTDVALID)
        $xml = @simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NONET);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if ($xml === false || ! empty($errors)) {
            throw new InvalidArgumentException('Format XML shared strings tidak valid atau rusak.');
        }

        $sharedStrings = [];
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $text = (string) $si->t;
            } elseif (isset($si->r)) {
                $text = '';
                foreach ($si->r as $rPart) {
                    $text .= (string) $rPart->t;
                }
            } else {
                $text = '';
            }

            // Batasi panjang karakter teks per sel (maksimal 1.000 karakter)
            if (strlen($text) > 1000) {
                throw new InvalidArgumentException('Panjang teks dalam sel melebihi batas maksimum 1.000 karakter.');
            }

            $sharedStrings[] = $text;
        }

        return $sharedStrings;
    }

    /**
     * Memparsing XML Worksheet dengan batas baris, kolom, dan panjang sel (SC-04, SC-05).
     */
    private function parseWorksheetXml(string $xmlContent, array $sharedStrings): array
    {
        $this->assertSafeXmlContent($xmlContent);

        libxml_use_internal_errors(true);
        // HANYA LIBXML_NONET (tanpa LIBXML_NOENT)
        $xml = @simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NONET);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if ($xml === false || ! empty($errors) || ! isset($xml->sheetData)) {
            throw new InvalidArgumentException('Format XML lembar kerja tidak valid atau rusak.');
        }

        $rows = [];
        $rowCount = 0;

        foreach ($xml->sheetData->row as $rowNode) {
            $rowCount++;
            // Batas maksimal baris (maksimal 5.000 baris) (SC-04)
            if ($rowCount > 5000) {
                throw new InvalidArgumentException('Jumlah baris dalam berkas melebihi batas maksimum 5.000 baris.');
            }

            $rowCells = [];
            $colCount = 0;

            foreach ($rowNode->c as $cell) {
                $colCount++;
                // Batas maksimal kolom (maksimal 50 kolom) (SC-04)
                if ($colCount > 50) {
                    throw new InvalidArgumentException('Jumlah kolom dalam lembar kerja melebihi batas maksimum 50 kolom.');
                }

                $cellRef = (string) $cell['r'];
                $colLetters = preg_replace('/[0-9]/', '', $cellRef);
                $colIndex = $this->columnLetterToIndex($colLetters);

                $cellType = (string) $cell['t'];
                // Abaikan elemen formula <f> sama sekali demi keamanan! Hanya ambil <v> atau inline text
                $val = isset($cell->v) ? (string) $cell->v : '';

                if ($cellType === 's') {
                    $stringIndex = (int) $val;
                    $cellValue = $sharedStrings[$stringIndex] ?? '';
                } elseif ($cellType === 'inlineStr' && isset($cell->is->t)) {
                    $cellValue = (string) $cell->is->t;
                } else {
                    $cellValue = $val;
                }

                // Batas panjang karakter sel (maksimal 1.000 karakter)
                if (strlen($cellValue) > 1000) {
                    throw new InvalidArgumentException('Panjang teks dalam sel melebihi batas maksimum 1.000 karakter.');
                }

                $rowCells[$colIndex] = $cellValue;
            }

            if (! empty($rowCells)) {
                $maxCol = max(array_keys($rowCells));
                if ($maxCol > 50) {
                    throw new InvalidArgumentException('Rentang kolom melebihi batas maksimum 50 kolom.');
                }

                $fullRow = [];
                for ($c = 0; $c <= $maxCol; $c++) {
                    $fullRow[$c] = $rowCells[$c] ?? '';
                }
                $rows[] = $fullRow;
            }
        }

        return $rows;
    }

    /**
     * Memeriksa dan memastikan string XML aman dari DTD, XXE, Entity Substitution, dan pranala eksternal (CR-01, SC-02, SC-03).
     */
    private function assertSafeXmlContent(string $xmlContent): void
    {
        // 1. Tolak Deklarasi DTD (<!DOCTYPE) dan Deklarasi Entitas (<!ENTITY)
        if (preg_match('/<!DOCTYPE/i', $xmlContent) || preg_match('/<!ENTITY/i', $xmlContent)) {
            throw new InvalidArgumentException('Dokumen XML memuat DTD atau deklarasi entitas yang dilarang demi keamanan (XXE Protection).');
        }

        // 2. Tolak Entitas Eksternal (SYSTEM / PUBLIC)
        if (preg_match('/\b(SYSTEM|PUBLIC)\b/i', $xmlContent)) {
            throw new InvalidArgumentException('Dokumen XML memuat referensi entitas eksternal yang dilarang (XXE Protection).');
        }

        // 3. Tolak Relasi Eksternal (TargetMode="External" atau protokol eksternal dalam XML)
        if (preg_match('/TargetMode\s*=\s*["\']External["\']/i', $xmlContent)) {
            throw new InvalidArgumentException('Dokumen XML memuat tautan luar (external link) yang dilarang.');
        }
    }

    /**
     * Konversi huruf kolom Excel (A, B, C, ..., Z, AA, AB) ke integer index 0-based.
     */
    private function columnLetterToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $len = strlen($letters);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }
}
