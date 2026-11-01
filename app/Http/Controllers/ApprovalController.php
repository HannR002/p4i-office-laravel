<?php

namespace App\Http\Controllers;

use App\Exceptions\StaleRevisionException;
use App\Models\ApprovalRequest;
use App\Models\Letter;
use App\Models\SignatoryMandate;
use App\Services\ApprovalWorkflowService;
use App\Services\MandateValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalWorkflowService $workflowService,
        protected MandateValidationService $mandateService
    ) {}

    /**
     * Menampilkan antrean surat yang menunggu pemeriksaan oleh reviewer berwenang.
     * M2-FR-03, AP2-02, AP2-13.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        // 1. Verifikasi RBAC: Hanya Reviewer, Pengurus Yayasan, atau Admin yang memiliki akses
        $hasReviewerRole = $user->hasRole('REVIEWER') || $user->hasRole('PENGURUS_YAYASAN') || $user->isSystemAdmin();
        $hasActiveMandate = SignatoryMandate::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->whereNull('revoked_at')
            ->whereDate('valid_from', '<=', now()->format('Y-m-d'))
            ->whereDate('valid_to', '>=', now()->format('Y-m-d'))
            ->exists();

        if (! $hasReviewerRole && ! $hasActiveMandate) {
            abort(403, 'Akses ditolak: Hanya reviewer berwenang atau pengurus yayasan yang dapat mengakses antrean pemeriksaan.');
        }

        // 2. Query permohonan persetujuan aktif
        $query = ApprovalRequest::where('organization_id', $user->organization_id)
            ->whereIn('status', ['PENDING', 'IN_REVIEW'])
            ->with(['letter.letterType', 'letter.latestRevision', 'submitter', 'claimedBy'])
            ->whereHas('letter', function ($q) use ($user) {
                // Jangan tampilkan surat buatan sendiri (Cegah Self-Approval pada antrean)
                $q->where('author_id', '!=', $user->id);

                // Isolasi kerahasiaan: Surat RAHASIA hanya untuk pembuat atau pengurus/admin
                if (! $user->isSystemAdmin() && ! $user->hasRole('PENGURUS_YAYASAN')) {
                    $q->where('classification', '!=', 'RAHASIA');
                }
            });

        // Filter tab status
        $statusFilter = $request->query('status');
        if ($statusFilter === 'PENDING') {
            $query->where('status', 'PENDING');
        } elseif ($statusFilter === 'IN_REVIEW') {
            $query->where('status', 'IN_REVIEW');
        }

        $pendingRequests = $query->orderBy('submitted_at', 'asc')->paginate(15)->withQueryString();

        return view('approvals.index', compact('pendingRequests', 'user', 'statusFilter'));
    }

    /**
     * Menampilkan lembar telaah dan aksi keputusan persetujuan untuk surat tertentu.
     */
    public function show(Letter $letter): View
    {
        $user = Auth::user();

        if ($user->organization_id !== $letter->organization_id) {
            abort(403, 'Akses lintas organisasi ditolak.');
        }

        // Pencegahan Self-Approval
        if ($letter->author_id === $user->id) {
            abort(403, 'Pembuat draf dilarang memeriksa atau menyetujui pengajuannya sendiri (Self-approval ditolak).');
        }

        // Proteksi Dokumen CONFIDENTIAL / RAHASIA (STG-04)
        if (in_array($letter->classification, ['CONFIDENTIAL', 'RAHASIA'], true)) {
            if (! $this->mandateService->canAccessConfidential($user, $letter, 'VIEW')) {
                abort(403, 'Akses ditolak: Dokumen rahasia hanya dapat ditelaah oleh pejabat bermandat aktif yang sah.');
            }
        }

        // Cari permohonan persetujuan aktif atau riwayat permohonan terkini
        $approvalRequest = ApprovalRequest::where('letter_id', $letter->id)
            ->with(['steps.reviewer', 'steps.mandate', 'claimedBy'])
            ->latest()
            ->first();

        // Periksa apakah user memiliki mandat review atau approve
        $canReview = $this->mandateService->canReview($user, $letter);
        $canApprove = $this->mandateService->canApprove($user, $letter);

        $letter->load(['letterType', 'latestRevision', 'author', 'template']);

        return view('approvals.show', compact('letter', 'approvalRequest', 'canReview', 'canApprove', 'user'));
    }

    /**
     * Mengklaim draf surat untuk mulai diperiksa oleh reviewer.
     * Transisi: SUBMITTED -> IN_REVIEW.
     */
    public function claim(Letter $letter): RedirectResponse
    {
        $user = Auth::user();

        $this->workflowService->claimReview($user, $letter);

        return redirect()->route('approvals.show', $letter)
            ->with('success', 'Pemeriksaan draf surat telah berhasil dimulai.');
    }

    /**
     * Memproses keputusan persetujuan reviewer (APPROVE, CHANGES_REQUESTED, REJECT).
     * M2-FR-05, M2-FR-07, M2-FR-08.
     */
    public function decision(Request $request, Letter $letter): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'decision' => ['required', 'in:APPROVED,CHANGES_REQUESTED,REJECTED'],
            'comments' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'expected_version' => ['nullable', 'integer'],
            'expected_sha256' => ['nullable', 'string', 'size:64'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            if ($validated['decision'] === 'APPROVED') {
                $this->workflowService->approve($user, $letter, $validated);
                $message = 'Draf surat berhasil disetujui (APPROVED). Catatan: nomor surat tetap belum diterbitkan.';
            } elseif ($validated['decision'] === 'CHANGES_REQUESTED') {
                $this->workflowService->requestChanges($user, $letter, $validated);
                $message = 'Draf surat berhasil dikembalikan kepada pembuat untuk perbaikan (CHANGES_REQUESTED).';
            } else {
                $this->workflowService->reject($user, $letter, $validated);
                $message = 'Draf surat telah ditolak secara definitif (REJECTED).';
            }

            return redirect()->route('approvals.index')->with('success', $message);
        } catch (StaleRevisionException $e) {
            return redirect()->route('approvals.show', $letter)
                ->withErrors(['conflict' => $e->getMessage()])
                ->with('error', $e->getMessage());
        }
    }
}
