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
     * Menampilkan daftar batch impor staging register historis (NR-05).
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
     * Menampilkan form unggah berkas CSV register historis.
     */
    public function create(): View
    {
        return view('legacy.create');
    }

    /**
     * Memproses unggahan berkas register historis ke staging (NR-05, NR-06, NR-07, NR-17).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'register_file' => 'required|file|mimes:csv,txt|max:5120',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $batch = $this->legacyService->importCsv(
                $user,
                $request->file('register_file'),
                $request->input('notes')
            );

            $msg = "Batch impor berhasil dimuat ke staging. Total: {$batch->total_rows} baris ({$batch->valid_rows} valid, {$batch->conflict_rows} anomali/konflik).";

            return redirect()->route('legacy.show', $batch->id)
                ->with('status', $msg);
        } catch (\Exception $e) {
            return back()->withErrors(['register_file' => $e->getMessage()]);
        }
    }

    /**
     * Menampilkan detail baris staging register historis dan inspeksi anomali (NR-06, NR-07).
     */
    public function show(Request $request, string $id): View
    {
        $user = Auth::user();

        $batch = LegacyRegisterBatch::where('organization_id', $user->organization_id)
            ->with(['importer'])
            ->findOrFail($id);

        $filter = $request->query('filter', 'all');

        $rowsQuery = LegacyRegisterRow::where('batch_id', $batch->id);

        if ($filter === 'valid') {
            $rowsQuery->where('is_valid', true);
        } elseif ($filter === 'conflict') {
            $rowsQuery->where('is_valid', false);
        }

        $rows = $rowsQuery->orderBy('row_index')->paginate(25);

        return view('legacy.show', compact('batch', 'rows', 'filter'));
    }

    /**
     * Memverifikasi batch oleh reviewer arsip resmi (NR-07).
     */
    public function verify(Request $request, string $id): RedirectResponse
    {
        $user = Auth::user();

        // RBAC: Hanya Reviewer atau Admin yang berhak memverifikasi
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
