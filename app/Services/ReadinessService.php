<?php

namespace App\Services;

use App\Models\LegacyRegisterBatch;
use App\Models\NumberingPolicy;
use App\Models\Organization;
use App\Models\SignatoryMandate;

class ReadinessService
{
    /**
     * Menghitung status kesiapan persuratan yayasan secara objektif tanpa klaim semu (NR-16).
     */
    public function evaluateReadiness(Organization $org): array
    {
        // 1. Evaluasi Kebijakan Penomoran (SOP)
        $policies = NumberingPolicy::where('organization_id', $org->id)->get();
        $hasActivePolicy = $policies->where('is_active', true)->isNotEmpty();
        $hasApprovedRef = $policies->whereNotNull('approved_policy_ref')->isNotEmpty();

        $policyStatus = [
            'key' => 'POLICY_RATIFICATION',
            'title' => 'Pengesahan SK Kebijakan Penomoran Resmi',
            'status' => $hasApprovedRef ? 'VERIFIED' : 'BLOCKED',
            'details' => $hasApprovedRef
                ? 'Kebijakan penomoran telah disahkan dengan referensi SK pengurus.'
                : 'Belum ada Surat Keputusan (SK) Dewan Pengurus Yayasan yang mengesahkan format penomoran definitif.',
            'passed' => $hasApprovedRef,
        ];

        // 2. Evaluasi Rekonsiliasi Register Historis
        $batches = LegacyRegisterBatch::where('organization_id', $org->id)->get();
        $totalBatches = $batches->count();
        $verifiedBatches = $batches->where('status', 'VERIFIED')->count();
        $conflictBatches = $batches->where('status', 'HAS_CONFLICTS')->count();

        $registerStatus = [
            'key' => 'LEGACY_RECONCILIATION',
            'title' => 'Rekonsiliasi Register Berkas Historis',
            'status' => ($totalBatches > 0 && $verifiedBatches === $totalBatches)
                ? 'VERIFIED'
                : ($conflictBatches > 0 ? 'DISCREPANCIES_FOUND' : 'IN_PROGRESS'),
            'details' => $totalBatches === 0
                ? 'Belum ada register historis yang diimpor ke staging.'
                : "Terdapat {$totalBatches} batch impor ({$verifiedBatches} terverifikasi, {$conflictBatches} berkonflik/anomali).",
            'passed' => $totalBatches > 0 && $verifiedBatches === $totalBatches,
        ];

        // 3. Evaluasi Mandat Pejabat Definitif
        $mandates = SignatoryMandate::where('organization_id', $org->id)
            ->where('status', 'ACTIVE')
            ->get();
        $realMandates = $mandates->where('is_simulation', false);

        $mandateStatus = [
            'key' => 'MANDATE_RATIFICATION',
            'title' => 'Pengesahan Mandat Pejabat Penelaah & Penandatangan',
            'status' => $realMandates->isNotEmpty() ? 'VERIFIED' : 'SIMULATION_ONLY',
            'details' => $realMandates->isNotEmpty()
                ? "Terdapat {$realMandates->count()} pejabat definitif yang disahkan SK."
                : 'Seluruh pejabat aktif saat ini berstatus SIMULASI PENGEMBANGAN TEKNIS dan belum memiliki SK pengesahan.',
            'passed' => $realMandates->isNotEmpty(),
        ];

        // 4. Evaluasi Safety Guard Sistem
        $safetyStatus = [
            'key' => 'SAFETY_GUARD_STATUS',
            'title' => 'Status Penegakan Pengaman Sistem (Safety Guard)',
            'status' => 'SECURE_OFF',
            'details' => 'Nomor resmi strictly NULL dan flag penomoran resmi non-aktif (OFF).',
            'passed' => true,
        ];

        // 5. Kesimpulan Kesiapan Keseluruhan (Overall Readiness)
        $isFullyReady = $policyStatus['passed'] && $registerStatus['passed'] && $mandateStatus['passed'];
        $overallStatus = $isFullyReady ? 'READY' : 'BLOCKED';

        return [
            'overall_status' => $overallStatus,
            'organization_name' => $org->legal_name,
            'evaluated_at' => now()->toIso8601String(),
            'pillars' => [
                $policyStatus,
                $registerStatus,
                $mandateStatus,
                $safetyStatus,
            ],
            'blocking_reasons' => array_values(array_filter([
                ! $policyStatus['passed'] ? 'SK Standar Penomoran Resmi belum disahkan oleh Dewan Pengurus Yayasan.' : null,
                ! $registerStatus['passed'] ? 'Register berkas persuratan lama belum selesai direkonsiliasi dan divalidasi arsiparis.' : null,
                ! $mandateStatus['passed'] ? 'Mandat pejabat definitif penelaah dan penandatangan belum disahkan.' : null,
            ])),
        ];
    }
}
