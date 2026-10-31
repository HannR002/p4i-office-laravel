<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\NumberingPolicy;
use App\Services\NumberingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NumberingPolicyController extends Controller
{
    public function __construct(
        protected NumberingService $numberingService
    ) {}

    /**
     * Menampilkan daftar kebijakan penomoran surat dan simulator nomor.
     */
    public function index(): View
    {
        $user = Auth::user();

        $policies = NumberingPolicy::where('organization_id', $user->organization_id)
            ->with(['counters', 'simulations'])
            ->get();

        $previews = [];
        foreach ($policies as $policy) {
            $previews[$policy->id] = $this->numberingService->previewSimulation($policy, 1);
        }

        return view('numbering.policies', compact('policies', 'previews'));
    }

    /**
     * Menyimpan rancangan kebijakan penomoran baru (NR-01).
     * Selalu disimpan dengan is_active = false dan status = DRAFT.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // RBAC: Hanya Admin atau Pengurus Yayasan yang dapat membuat policy
        if (! $user->isSystemAdmin() && ! $user->hasRole('PENGURUS_YAYASAN')) {
            abort(403, 'Hanya Administrator atau Pengurus Yayasan yang dapat membuat kebijakan penomoran.');
        }

        $validated = $request->validate([
            'series_key' => 'required|string|max:64',
            'code' => 'required|string|max:40',
            'format_pattern' => 'required|string|max:255',
            'reset_period' => 'required|in:YEAR,MONTH',
            'padding' => 'required|integer|min:1|max:8',
            'scope_type' => 'nullable|string|max:32',
            'description' => 'nullable|string|max:500',
        ]);

        // Safety Invariant: is_active selalu FALSE dan status DRAFT
        $policy = NumberingPolicy::create([
            'organization_id' => $user->organization_id,
            'series_key' => strtoupper(trim($validated['series_key'])),
            'code' => strtoupper(trim($validated['code'])),
            'format_pattern' => trim($validated['format_pattern']),
            'reset_period' => $validated['reset_period'],
            'padding' => $validated['padding'],
            'is_active' => false,
            'status' => 'DRAFT',
            'scope_type' => $validated['scope_type'] ?? 'SERIES',
            'description' => $validated['description'] ?? null,
            'legacy_reconciled' => false,
        ]);

        AuditEvent::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'event_type' => 'NUMBERING_POLICY_DRAFT_CREATED',
            'entity_type' => 'numbering_policies',
            'entity_id' => $policy->id,
            'payload' => [
                'series_key' => $policy->series_key,
                'code' => $policy->code,
                'format_pattern' => $policy->format_pattern,
                'is_active' => false,
                'status' => 'DRAFT',
            ],
        ]);

        return redirect()->route('numbering.policies.index')
            ->with('status', 'Draf kebijakan penomoran baru berhasil disimpan (Status: DRAFT, Non-Aktif).');
    }

    /**
     * Endpoint API pratinjau string penomoran dinamis (NR-02, NR-03, NR-04).
     */
    public function preview(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'policy_id' => 'required|uuid',
            'seq' => 'nullable|integer|min:1',
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $policy = NumberingPolicy::where('organization_id', $user->organization_id)
            ->findOrFail($validated['policy_id']);

        $preview = $this->numberingService->previewSimulation(
            $policy,
            $validated['seq'] ?? 1,
            $validated['month'] ?? null,
            $validated['year'] ?? null
        );

        return response()->json([
            'policy_id' => $policy->id,
            'preview' => $preview,
            'is_simulation' => true,
        ]);
    }

    /**
     * Endpoint simulasi alokasi nomor dengan penguncian konkurensi (NR-09, NR-10).
     */
    public function simulate(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'policy_id' => 'required|uuid',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        $policy = NumberingPolicy::where('organization_id', $user->organization_id)
            ->findOrFail($validated['policy_id']);

        $simulation = $this->numberingService->allocateSimulationNumber(
            $user,
            $policy,
            null,
            $validated['idempotency_key'] ?? null
        );

        return response()->json([
            'success' => true,
            'simulation_id' => $simulation->id,
            'simulated_number' => $simulation->simulated_number,
            'sequence_number' => $simulation->sequence_number,
            'idempotency_key' => $simulation->idempotency_key,
            'is_simulation' => true,
        ]);
    }
}
