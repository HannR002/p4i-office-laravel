<?php

namespace App\Http\Controllers;

use App\Models\LegacyRegisterBatch;
use App\Models\LegacyRegisterRow;
use App\Services\LegacyRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LegacyRegisterController extends Controller
{
    public function __construct(
        protected LegacyRegisterService $legacyService
    ) {}

    /**
     * Menampilkan daftar batch impor staging register historis (BR-02, BR-06).
     */
    public function index(): View
    {
        $user = Auth::user();

        $batches = LegacyRegisterBatch::where('organization_id', $user->organization_id)
            ->with(['importer'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('legacy.index', compact('batches'));
    }

    /**
     * Menampilkan form unggah berkas CSV/XLSX register historis.
     */
    public function create(): View
    {
        return view('legacy.create');
    }

    /**
     * Memproses unggahan berkas register historis (CSV atau XLSX) ke staging (BR-02, BR-06, BR-17).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'register_file' => 'required|file|max:5120',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $batch = $this->legacyService->importFile(
                $user,
                $request->file('register_file'),
                $request->input('notes')
            );

            $msg = "Batch impor berkas ({$batch->source_filename}) berhasil dimuat ke staging. Total: {$batch->total_rows} baris ({$batch->valid_rows} valid, {$batch->conflict_rows} anomali/konflik).";

            return redirect()->route('legacy.show', $batch->id)
                ->with('status', $msg);
        } catch (\Exception $e) {
            return back()->withErrors(['register_file' => $e->getMessage()]);
        }
    }

    /**
     * Menampilkan detail baris staging register historis dan inspeksi anomali (BR-06, BR-07, BR-09).
     */
    public function show(Request $request, string $id): View
    {
        $user = Auth::user();

        $batch = LegacyRegisterBatch::where('organization_id', $user->organization_id)
            ->with(['importer'])
            ->findOrFail($id);

        $filter = $request->query('filter', 'all');

        $rowsQuery = LegacyRegisterRow::where('batch_id', $batch->id)->with(['auditLogs.actor']);

        if ($filter === 'valid') {
            $rowsQuery->where('is_valid', true);
        } elseif ($filter === 'conflict') {
            $rowsQuery->where('is_valid', false)->orWhere('verification_status', 'CONFLICT');
        } elseif ($filter === 'verified') {
            $rowsQuery->where('verification_status', 'VERIFIED');
        }

        $rows = $rowsQuery->orderBy('row_index')->paginate(25);

        return view('legacy.show', compact('batch', 'rows', 'filter'));
    }

    /**
     * Rekonsiliasi individual baris register oleh Tim Arsiparis / Reviewer resmi (BR-07, BR-08, BR-09).
     */
    public function reconcileRow(Request $request, string $rowId): RedirectResponse
    {
        $user = Auth::user();

        // RBAC: Hanya Reviewer, Arsiparis, atau Admin yang dapat merekonsiliasi baris
        if (! $user->isSystemAdmin() && ! $user->hasRole('REVIEWER') && ! $user->hasRole('PENGURUS_YAYASAN')) {
            abort(403, 'Hanya Reviewer atau Administrator yang memiliki hak merekonsiliasi berkas register.');
        }

        $validated = $request->validate([
            'status' => 'required|in:VERIFIED,CONFLICT,UNVERIFIED',
            'reconciliation_action' => 'required|in:RETAIN_AS_IS,AMEND_OFFICIAL_RECORD,VOID_ENTRY,FLAGGED_FOR_BOARD',
            'notes' => 'required|string|max:500',
            'board_resolution_ref' => 'nullable|string|max:255',
        ]);

        $row = LegacyRegisterRow::whereHas('batch', fn ($q) => $q->where('organization_id', $user->organization_id))
            ->findOrFail($rowId);

        $this->legacyService->reconcileRow(
            $user,
            $row,
            $validated['status'],
            $validated['reconciliation_action'],
            $validated['notes'],
            $validated['board_resolution_ref'] ?? null
        );

        return back()->with('status', "Baris nomor '{$row->raw_number}' berhasil direkonsiliasi (Status: {$validated['status']}).");
    }

    /**
     * Memverifikasi batch secara menyeluruh oleh reviewer arsip resmi.
     */
    public function verify(Request $request, string $id): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isSystemAdmin() && ! $user->hasRole('REVIEWER') && ! $user->hasRole('PENGURUS_YAYASAN')) {
            abort(403, 'Hanya Reviewer atau Administrator yang berhak memverifikasi berkas register.');
        }

        $batch = LegacyRegisterBatch::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $request->validate([
            'decision' => 'required|in:APPROVE,REJECT',
            'notes' => 'required|string|max:500',
        ]);

        $approved = $request->input('decision') === 'APPROVE';
        $this->legacyService->verifyBatch($user, $batch, $approved, $request->input('notes'));

        $statusText = $approved ? 'DIVERIFIKASI (VERIFIED)' : 'DITOLAK (REJECTED)';

        return redirect()->route('legacy.show', $batch->id)
            ->with('status', "Status batch berhasil diperbarui menjadi: {$statusText}.");
    }
}
