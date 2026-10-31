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
use SimpleXMLElement;
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
     * Rekonsiliasi individual baris register lama oleh Tim Arsiparis / Reviewer resmi (BR-07, BR-08, BR-09).
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

        $prevStatus = $row->verification_status;

        return DB::transaction(function () use ($user, $row, $status, $action, $notes, $boardRef, $prevStatus) {
            $row->verification_status = $status;
            $row->reconciliation_action = $action;
            $row->reconciliation_notes = $notes;
            $row->board_resolution_ref = $boardRef;
            $row->verified_by = $user->id;
            $row->verified_at = now();
            $row->save();

            // Catat log rekonsiliasi immutable
            ReconciliationAuditLog::create([
                'row_id' => $row->id,
                'user_id' => $user->id,
                'previous_status' => $prevStatus,
                'new_status' => $status,
                'action_taken' => $action,
                'notes' => $notes,
            ]);

            // Sinkronkan status batch
            $batch = $row->batch;
            $hasConflict = LegacyRegisterRow::where('batch_id', $batch->id)
                ->where('verification_status', 'CONFLICT')
                ->exists();

            $allVerified = ! LegacyRegisterRow::where('batch_id', $batch->id)
                ->where('verification_status', '!=', 'VERIFIED')
                ->exists();

            $batch->status = $allVerified ? 'VERIFIED' : ($hasConflict ? 'HAS_CONFLICTS' : 'STAGED');
            $batch->save();

            return $row;
        });
    }

    /**
     * Memverifikasi batch oleh reviewer arsip resmi.
     */
    public function verifyBatch(User $user, LegacyRegisterBatch $batch, bool $approved, string $notes): void
    {
        if ($batch->organization_id !== $user->organization_id) {
            throw new DomainException('Batch register bukan milik organisasi pengguna.');
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
     * Pembaca berkas CSV secara aman.
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new DomainException('Gagal membuka berkas CSV register');
        }

        $rows = [];
        while (($data = fgetcsv($handle, 4096, ',')) !== false) {
            $rows[] = $data;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Parser XLSX asli dan aman menggunakan ZipArchive & SimpleXMLElement (BR-02).
     * Tidak mengevaluasi formula, macro, atau kode eksternal.
     */
    private function parseXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Berkas XLSX rusak atau tidak memiliki struktur zip yang valid.');
        }

        // Keamanan: Tolak jika memuat berkas macro VBA
        if ($zip->locateName('xl/vbaProject.bin') !== false) {
            $zip->close();
            throw new InvalidArgumentException('Berkas XLSX memuat komponen Macro/VBA yang dilarang oleh kebijakan keamanan sistem.');
        }

        // 1. Ekstrak shared strings jika ada
        $sharedStrings = [];
        $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($stringsXml !== false) {
            $xml = @simplexml_load_string($stringsXml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
            if ($xml) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $tPart = '';
                        foreach ($si->r as $rPart) {
                            $tPart .= (string) $rPart->t;
                        }
                        $sharedStrings[] = $tPart;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Ekstrak lembar kerja utama (sheet1.xml)
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $zip->close();
            throw new InvalidArgumentException('Lembar kerja utama (sheet1.xml) tidak ditemukan dalam berkas XLSX.');
        }

        $xml = @simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
        $zip->close();

        if (! $xml || ! isset($xml->sheetData)) {
            throw new InvalidArgumentException('Struktur XML lembar kerja tidak valid.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $rowCells = [];
            foreach ($rowNode->c as $cell) {
                $cellRef = (string) $cell['r'];
                $colLetters = preg_replace('/[0-9]/', '', $cellRef);
                $colIndex = $this->columnLetterToIndex($colLetters);

                $cellType = (string) $cell['t'];
                $val = isset($cell->v) ? (string) $cell->v : '';

                if ($cellType === 's') {
                    $stringIndex = (int) $val;
                    $cellValue = $sharedStrings[$stringIndex] ?? '';
                } elseif ($cellType === 'inlineStr' && isset($cell->is->t)) {
                    $cellValue = (string) $cell->is->t;
                } else {
                    $cellValue = $val;
                }

                $rowCells[$colIndex] = $cellValue;
            }

            if (! empty($rowCells)) {
                $maxCol = max(array_keys($rowCells));
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
