<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\LegacyRegisterBatch;
use App\Models\Letter;
use App\Models\NumberingPolicy;
use App\Models\NumberingSimulation;
use App\Models\NumberingSimulationCounter;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NumberingService
{
    private const ROMAN_MONTHS = [
        1 => 'I',
        2 => 'II',
        3 => 'III',
        4 => 'IV',
        5 => 'V',
        6 => 'VI',
        7 => 'VII',
        8 => 'VIII',
        9 => 'IX',
        10 => 'X',
        11 => 'XI',
        12 => 'XII',
    ];

    /**
     * Konversi integer bulan (1..12) ke angka Romawi murni.
     */
    public function toRomanMonth(int $month): string
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Bulan harus berada dalam rentang 1 hingga 12. Diterima: {$month}");
        }

        return self::ROMAN_MONTHS[$month];
    }

    /**
     * Membentuk preview string nomor simulasi berdasarkan pola format kebijakan.
     * Hasil SELALU diawali dengan [SIMULASI] sesuai amanat NR-02 dan NR-19.
     */
    public function previewSimulation(
        NumberingPolicy $policy,
        int $seq = 1,
        ?int $month = null,
        ?int $year = null
    ): string {
        $m = $month ?? (int) now()->format('n');
        $y = $year ?? (int) now()->format('Y');

        if ($m < 1 || $m > 12) {
            throw new InvalidArgumentException("Bulan tidak valid: {$m}");
        }

        $romanMonth = $this->toRomanMonth($m);
        $paddedSeq = str_pad((string) $seq, $policy->padding ?: 3, '0', STR_PAD_LEFT);

        $pattern = $policy->format_pattern ?: '{seq:03}/{code}/{month_roman}/{year}';

        $rendered = preg_replace_callback('/\{seq:(\d+)\}/', function ($matches) use ($seq) {
            $pad = (int) $matches[1];

            return str_pad((string) $seq, $pad, '0', STR_PAD_LEFT);
        }, $pattern);

        $replacements = [
            '{seq}' => $paddedSeq,
            '{code}' => $policy->code,
            '{month_roman}' => $romanMonth,
            '{year}' => (string) $y,
            '{month}' => str_pad((string) $m, 2, '0', STR_PAD_LEFT),
        ];

        $resultNumber = str_replace(array_keys($replacements), array_values($replacements), $rendered);

        return "[SIMULASI] {$resultNumber}";
    }

    /**
     * Mengalokasikan nomor simulasi atomik dan aman dari konkurensi (NR-09, NR-10, NR-15).
     * Menerapkan penguncian baris pesimistik MariaDB (lockForUpdate) dan idempotensi.
     */
    public function allocateSimulationNumber(
        User $user,
        NumberingPolicy $policy,
        ?Letter $letter = null,
        ?string $idempotencyKey = null,
        ?int $month = null,
        ?int $year = null
    ): NumberingSimulation {
        if ($policy->organization_id !== $user->organization_id) {
            throw new DomainException('Kebijakan penomoran bukan milik organisasi pengguna aktif.');
        }

        if ($letter && $letter->organization_id !== $user->organization_id) {
            throw new DomainException('Surat tidak berada pada organisasi yang sama.');
        }

        // 1. Cek idempotensi: jika kunci sudah ada, kembalikan record tanpa menaikkan counter
        if ($idempotencyKey) {
            $existing = NumberingSimulation::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $m = $month ?? (int) now()->format('n');
        $y = $year ?? (int) now()->format('Y');

        $scopeKey = $policy->series_key;
        $yearKey = $y;
        $monthKey = ($policy->reset_period === 'MONTH') ? $m : 0;

        return DB::transaction(function () use (
            $user,
            $policy,
            $letter,
            $idempotencyKey,
            $scopeKey,
            $yearKey,
            $monthKey,
            $m,
            $y
        ) {
            // Ambil counter dengan penguncian baris pesimistik (pessimistic row locking)
            $counter = NumberingSimulationCounter::where('policy_id', $policy->id)
                ->where('scope_key', $scopeKey)
                ->where('year_key', $yearKey)
                ->where('month_key', $monthKey)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                // Inisialisasi baris counter jika belum ada
                $counter = NumberingSimulationCounter::create([
                    'organization_id' => $user->organization_id,
                    'policy_id' => $policy->id,
                    'scope_key' => $scopeKey,
                    'year_key' => $yearKey,
                    'month_key' => $monthKey,
                    'last_number' => 0,
                ]);

                // Kunci ulang baris baru tersebut
                $counter = NumberingSimulationCounter::where('id', $counter->id)
                    ->lockForUpdate()
                    ->first();
            }

            $nextSequence = $counter->last_number + 1;
            $counter->last_number = $nextSequence;
            $counter->updated_at = now();
            $counter->save();

            // Bentuk string simulasi dengan prefiks [SIMULASI]
            $simulatedString = $this->previewSimulation($policy, $nextSequence, $m, $y);

            // Simpan riwayat simulasi
            $simulation = NumberingSimulation::create([
                'organization_id' => $user->organization_id,
                'policy_id' => $policy->id,
                'letter_id' => $letter?->id,
                'simulated_number' => $simulatedString,
                'sequence_number' => $nextSequence,
                'year' => $y,
                'month' => $m,
                'idempotency_key' => $idempotencyKey,
                'allocated_by' => $user->id,
            ]);

            // Catat audit event (NR-13)
            AuditEvent::create([
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'event_type' => 'NUMBERING_SIMULATION_ALLOCATED',
                'entity_type' => 'numbering_simulations',
                'entity_id' => $simulation->id,
                'payload' => [
                    'policy_id' => $policy->id,
                    'simulated_number' => $simulatedString,
                    'sequence_number' => $nextSequence,
                    'idempotency_key' => $idempotencyKey,
                ],
            ]);

            // SAFETY INVARIANT (NR-15):
            // Surat resmi TIDAK BOLEH diisi dengan nomor simulasi ini.
            if ($letter) {
                $letter->refresh();
                if ($letter->official_number !== null) {
                    throw new DomainException('SAFETY VIOLATION: official_number must remain NULL!');
                }
            }

            return $simulation;
        });
    }

    /**
     * Memastikan baseline tak terverifikasi DITOLAK saat mencoba digunakan sebagai pemula counter (NR-08).
     */
    public function seedFromLegacyBatch(NumberingPolicy $policy, LegacyRegisterBatch $batch): void
    {
        if ($batch->status !== 'VERIFIED') {
            throw new DomainException('Data register historis belum terverifikasi secara sah (Status: '.$batch->status.'). Baseline historis dilarang digunakan.');
        }

        // Jika terverifikasi oleh SK pengurus, dapat merekonsiliasi last_number kelak di Sprint M3B
        $policy->legacy_reconciled = true;
        $policy->save();
    }
}
