<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\LegacyRegisterBatch;
use App\Models\LegacyRegisterRow;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacyRegisterService
{
    /**
     * Memproses impor berkas CSV register historis ke tabel staging dengan deteksi anomali komprehensif.
     */
    public function importCsv(User $user, UploadedFile $file, ?string $notes = null): LegacyRegisterBatch
    {
        // 1. Validasi Keamanan Berkas (NR-17)
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt'])) {
            throw new InvalidArgumentException('Berkas harus berekstensi .csv atau .txt');
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Ukuran berkas melebihi batas maksimum 5 MB');
        }

        $filename = $file->getClientOriginalName();
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Baca konten berkas
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            throw new DomainException('Gagal membuka berkas register historis');
        }

        $rowsData = [];
        $header = fgetcsv($handle, 2048, ',');

        if (! $header) {
            fclose($handle);
            throw new InvalidArgumentException('Berkas CSV kosong atau tidak memiliki baris tajuk (header)');
        }

        // Normalisasi nama kolom header
        $header = array_map(function ($col) {
            return strtolower(trim((string) $col));
        }, $header);

        $numIdx = array_search('nomor_surat', $header) !== false ? array_search('nomor_surat', $header) : 0;
        $dateIdx = array_search('tanggal', $header) !== false ? array_search('tanggal', $header) : 1;
        $typeIdx = array_search('jenis', $header) !== false ? array_search('jenis', $header) : 2;
        $recipIdx = array_search('penerima', $header) !== false ? array_search('penerima', $header) : 3;
        $subjIdx = array_search('perihal', $header) !== false ? array_search('perihal', $header) : 4;

        $rowIndex = 1;
        $seenNumbersInBatch = [];

        while (($row = fgetcsv($handle, 2048, ',')) !== false) {
            if (empty(array_filter($row))) {
                continue; // Lewati baris kosong
            }

            $rawNumber = isset($row[$numIdx]) ? trim((string) $row[$numIdx]) : '';
            $dateStr = isset($row[$dateIdx]) ? trim((string) $row[$dateIdx]) : '';
            $typeCode = isset($row[$typeIdx]) ? trim((string) $row[$typeIdx]) : '';
            $recipient = isset($row[$recipIdx]) ? trim((string) $row[$recipIdx]) : '';
            $subject = isset($row[$subjIdx]) ? trim((string) $row[$subjIdx]) : '';

            // Sanitasi CSV Injection (menghindari awalan formula =, +, -, @)
            $rawNumber = ltrim($rawNumber, "=+-@\t\r");
            $recipient = ltrim($recipient, "=+-@\t\r");
            $subject = ltrim($subject, "=+-@\t\r");

            // Validasi Anomali dan Konflik (NR-06)
            $isValid = true;
            $conflictType = null;
            $conflictDetails = null;
            $docDate = null;

            // Anomali 1: Nomor Kosong
            if ($rawNumber === '') {
                $isValid = false;
                $conflictType = 'EMPTY_NUMBER';
                $conflictDetails = 'Nomor surat pada baris ini kosong atau tidak terisi.';
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

            // Anomali 4: Validasi Tanggal
            if ($dateStr !== '') {
                try {
                    $parsedDate = Carbon::parse($dateStr);
                    $docDate = $parsedDate->format('Y-m-d');

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
            }

            $rowsData[] = [
                'row_index' => $rowIndex++,
                'raw_number' => $rawNumber,
                'normalized_number' => $rawNumber ? strtoupper(preg_replace('/\s+/', '', $rawNumber)) : null,
                'document_date' => $docDate,
                'letter_type_code' => $typeCode ?: null,
                'recipient' => $recipient ?: null,
                'subject' => $subject ?: null,
                'is_valid' => $isValid,
                'conflict_type' => $conflictType,
                'conflict_details' => $conflictDetails,
                'verification_status' => 'UNVERIFIED', // Default NR-08: Tidak terverifikasi
            ];
        }

        fclose($handle);

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

            // Catat Audit Trail (NR-13)
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
     * Memverifikasi baris atau batch register lama oleh reviewer arsip resmi.
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
}
