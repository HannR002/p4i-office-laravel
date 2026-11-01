<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\DocumentExport;
use App\Models\Letter;
use App\Services\DocumentExportService;
use App\Services\MandateValidationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentArchiveController extends Controller
{
    public function __construct(
        protected DocumentExportService $exportService,
        protected MandateValidationService $mandateService
    ) {}

    /**
     * Memproses permintaan pembuatan ekspor draf surat (PDF atau DOCX) (M4A-01 s/d M4A-06).
     */
    public function export(Request $request, Letter $letter): RedirectResponse
    {
        $user = Auth::user();

        // 1. Verifikasi Tenancy Multi-Organisasi (STG-04)
        if ($letter->organization_id !== $user->organization_id) {
            abort(403, 'Akses ditolak: Surat bukan milik organisasi pengguna.');
        }

        // 2. Proteksi Dokumen CONFIDENTIAL / RAHASIA (M4B-02, STG-04)
        if (in_array($letter->classification, ['CONFIDENTIAL', 'RAHASIA'], true)) {
            if (! $this->mandateService->canAccessConfidential($user, $letter, 'EXPORT')) {
                abort(403, 'Akses ditolak: Dokumen berklasifikasi RAHASIA hanya dapat diekspor oleh konseptor atau pemeriksa bermandat aktif yang sah.');
            }
        }

        $validated = $request->validate([
            'format' => ['required', 'string', 'in:PDF,DOCX,pdf,docx'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $format = strtoupper($validated['format']);

        try {
            $export = $this->exportService->exportLetter(
                $user,
                $letter,
                $format,
                $validated['idempotency_key'] ?? null
            );

            return redirect()->back()->with('success', "Ekspor draf {$format} (v{$export->revision_version}) berhasil dihasilkan dan diarsipkan ke penyimpanan privat.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan sistem saat memproses ekspor dokumen: '.$e->getMessage());
        }
    }

    /**
     * Mengunduh berkas arsip secara aman melalui streaming internal dengan verifikasi integritas SHA-256 (M4A-07 s/d M4A-11).
     */
    public function download(Request $request, DocumentExport $export): StreamedResponse
    {
        $user = Auth::user();

        // 1. Verifikasi Tenancy Multi-Organisasi (M4A-08)
        if ($export->organization_id !== $user->organization_id) {
            abort(403, 'Akses ditolak: Dokumen bukan milik organisasi pengguna.');
        }

        // 2. Proteksi Dokumen CONFIDENTIAL / RAHASIA (M4B-02, M4B-03, M4B-04)
        $letter = $export->letter;
        if (in_array($letter->classification, ['CONFIDENTIAL', 'RAHASIA'], true)) {
            if (! $this->mandateService->canAccessConfidential($user, $letter, 'DOWNLOAD')) {
                abort(403, 'Akses ditolak: Dokumen rahasia hanya dapat diakses oleh konseptor atau pemeriksa bermandat aktif yang sah.');
            }
        }

        // 3. Verifikasi Status Dokumen
        if (! $export->isReady()) {
            abort(400, "Dokumen ekspor berstatus {$export->status} dan belum siap diunduh.");
        }

        try {
            return $this->exportService->downloadExport($user, $export);
        } catch (DomainException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'tidak ditemukan')) {
                abort(404, $msg);
            }
            if (str_contains($msg, 'Integritas berkas gagal diverifikasi')) {
                abort(422, $msg);
            }
            abort(400, $msg);
        }
    }

    /**
     * Menampilkan daftar arsip digital privat dengan filter pencarian dan paginasi (M4A-14).
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = DocumentExport::where('organization_id', $user->organization_id)
            ->with(['letter', 'exporter']);

        // Penegakan Kerahasiaan (Confidentiality Scoping - M4B-04, STG-04):
        // Dokumen rahasia hanya dapat dilihat oleh pembuat (author) atau pemeriksa yang memegang mandat aktif yang sah.
        $query->whereHas('letter', function ($q) use ($user) {
            $this->mandateService->applyConfidentialityScope($q, $user);
        });

        // Filter Format
        if ($request->filled('format') && in_array(strtoupper($request->format), ['PDF', 'DOCX'], true)) {
            $query->where('format', strtoupper($request->format));
        }

        // Filter Status
        if ($request->filled('status') && in_array($request->status, ['READY', 'PENDING', 'ERROR'], true)) {
            $query->where('status', $request->status);
        }

        // Filter Rentang Tanggal
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Pencarian Teks Bebas (Perihal Surat, Nama Penerima, atau File Hash)
        if ($request->filled('search')) {
            $searchTerm = '%'.trim($request->search).'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('file_hash', 'like', $searchTerm)
                    ->orWhere('id', 'like', $searchTerm)
                    ->orWhereHas('letter', function ($sub) use ($searchTerm) {
                        $sub->where('subject', 'like', $searchTerm)
                            ->orWhere('recipient_name', 'like', $searchTerm);
                    });
            });
        }

        $exports = $query->latest()->paginate(15)->withQueryString();

        // Ringkasan Statistik Penyimpanan Organisasi
        $totalFiles = DocumentExport::where('organization_id', $user->organization_id)->count();
        $readyFiles = DocumentExport::where('organization_id', $user->organization_id)->where('status', 'READY')->count();
        $totalBytes = (int) DocumentExport::where('organization_id', $user->organization_id)->where('status', 'READY')->sum('file_size');
        $pdfCount = DocumentExport::where('organization_id', $user->organization_id)->where('status', 'READY')->where('format', 'PDF')->count();
        $docxCount = DocumentExport::where('organization_id', $user->organization_id)->where('status', 'READY')->where('format', 'DOCX')->count();

        $stats = [
            'total_files' => $totalFiles,
            'ready_files' => $readyFiles,
            'total_size_mb' => round($totalBytes / (1024 * 1024), 2),
            'pdf_count' => $pdfCount,
            'docx_count' => $docxCount,
        ];

        return view('archives.index', compact('exports', 'user', 'stats'));
    }

    /**
     * Dashboard Administrasi Penyimpanan Arsip & Pengawasan Integritas Berkas (M4A-11, M4A-14).
     */
    public function admin(Request $request): View
    {
        $user = Auth::user();

        if (! $user->isSystemAdmin() && ! $user->hasRole('PENGURUS_YAYASAN')) {
            abort(403, 'Akses ditolak: Menu administrasi arsip hanya untuk Administrator dan Pengurus Yayasan.');
        }

        // Ringkasan Penyimpanan
        $totalExports = DocumentExport::where('organization_id', $user->organization_id)->count();
        $readyExports = DocumentExport::where('organization_id', $user->organization_id)->where('status', 'READY')->count();
        $totalBytes = (int) DocumentExport::where('organization_id', $user->organization_id)->where('status', 'READY')->sum('file_size');
        $totalDownloads = (int) DocumentExport::where('organization_id', $user->organization_id)->sum('download_count');

        $pdfCount = DocumentExport::where('organization_id', $user->organization_id)->where('status', 'READY')->where('format', 'PDF')->count();
        $docxCount = DocumentExport::where('organization_id', $user->organization_id)->where('status', 'READY')->where('format', 'DOCX')->count();

        // Riwayat Audit Aktivitas Ekspor & Unduh Terbaru (M4A-15)
        $auditLogs = AuditEvent::where('organization_id', $user->organization_id)
            ->whereIn('event_type', ['DOCUMENT_EXPORTED', 'DOCUMENT_DOWNLOADED', 'DOCUMENT_EXPORT_FAILED'])
            ->with('actor')
            ->latest('occurred_at')
            ->take(20)
            ->get();

        $stats = [
            'total_exports' => $totalExports,
            'ready_exports' => $readyExports,
            'total_size_mb' => round($totalBytes / (1024 * 1024), 2),
            'total_downloads' => $totalDownloads,
            'pdf_count' => $pdfCount,
            'docx_count' => $docxCount,
        ];

        return view('archives.admin', compact('user', 'stats', 'auditLogs'));
    }

    /**
     * Menjalankan audit verifikasi integritas arsip fisik (Disk vs DB SHA-256) (M4A-11).
     */
    public function verifyIntegrity(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isSystemAdmin() && ! $user->hasRole('PENGURUS_YAYASAN')) {
            abort(403, 'Akses ditolak: Verifikasi integritas arsip memerlukan kewenangan administratif.');
        }

        $result = $this->exportService->verifyArchiveIntegrity($user);

        return redirect()->back()->with('integrity_result', $result);
    }
}
