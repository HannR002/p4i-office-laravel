<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Services\ApprovalWorkflowService;
use App\Services\LetterDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LetterController extends Controller
{
    public function __construct(
        protected LetterDraftService $draftService,
        protected ApprovalWorkflowService $approvalService
    ) {}

    /**
     * Menampilkan daftar surat dinas berdasarkan kewenangan pengguna.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Letter::class);
        $user = Auth::user();

        $query = Letter::where('organization_id', $user->organization_id)
            ->with(['letterType', 'author', 'template']);

        // Penegakan Kerahasiaan (Confidentiality Scoping):
        // Jika bukan Admin dan bukan Pengurus Yayasan, sembunyikan dokumen RAHASIA milik orang lain.
        if (! $user->isSystemAdmin() && ! $user->hasRole('PENGURUS_YAYASAN')) {
            $query->where(function ($q) use ($user) {
                $q->where('classification', '!=', 'RAHASIA')
                    ->orWhere('author_id', $user->id);
            });
        }

        // Pencarian teks (perihal, nama penerima)
        if ($request->filled('search')) {
            $searchTerm = '%'.trim($request->search).'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('subject', 'like', $searchTerm)
                    ->orWhere('recipient_name', 'like', $searchTerm);
            });
        }

        // Filter klasifikasi
        if ($request->filled('classification') && in_array($request->classification, ['INTERNAL', 'TERBATAS', 'RAHASIA'], true)) {
            $query->where('classification', $request->classification);
        }

        // Filter jenis surat
        if ($request->filled('letter_type_id')) {
            $query->where('letter_type_id', $request->letter_type_id);
        }

        $letters = $query->latest()->paginate(15)->withQueryString();

        $letterTypes = LetterType::where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->orderBy('label')
            ->get();

        return view('letters.index', compact('letters', 'user', 'letterTypes'));
    }

    /**
     * Menampilkan formulir pembuatan draf surat baru.
     */
    public function create(): View
    {
        $this->authorize('create', Letter::class);
        $user = Auth::user();

        $letterTypes = LetterType::where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->with(['templates' => function ($q) {
                $q->where('is_active', true);
            }])
            ->get();

        $templates = LetterTemplate::where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->with('letterType')
            ->get();

        return view('letters.create', compact('user', 'letterTypes', 'templates'));
    }

    /**
     * Menyimpan draf surat baru beserta revisi v1 secara atomik.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Letter::class);
        $user = Auth::user();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'letter_type_id' => ['required', 'uuid', 'exists:letter_types,id'],
            'template_id' => ['required', 'uuid', 'exists:letter_templates,id'],
            'classification' => ['required', 'in:INTERNAL,TERBATAS,RAHASIA'],
            'planned_date' => ['required', 'date'],
            'variables' => ['nullable', 'array'],
        ]);

        $letter = $this->draftService->createDraft($user, $validated);

        return redirect()->route('letters.show', $letter)
            ->with('success', 'Draf surat berhasil dibuat (Versi 1).');
    }

    /**
     * Menampilkan detail draf surat, isi terkini, dan linimasa riwayat revisi.
     */
    public function show(Letter $letter): View
    {
        $this->authorize('view', $letter);

        $letter->load([
            'revisions.creator',
            'letterType',
            'template',
            'author.unit',
            'latestRevision',
        ]);

        $currentRevision = $letter->latestRevision;

        return view('letters.show', compact('letter', 'currentRevision'));
    }

    /**
     * Menampilkan formulir revisi draf surat.
     */
    public function edit(Letter $letter): View
    {
        $this->authorize('update', $letter);
        $user = Auth::user();

        $letter->load(['latestRevision', 'template', 'letterType']);

        $templates = LetterTemplate::where('organization_id', $user->organization_id)
            ->where('letter_type_id', $letter->letter_type_id)
            ->where('is_active', true)
            ->get();

        return view('letters.edit', compact('letter', 'templates'));
    }

    /**
     * Menyimpan revisi baru draf surat (v2, v3, dst.) secara append-only.
     */
    public function update(Request $request, Letter $letter): RedirectResponse
    {
        $this->authorize('update', $letter);
        $user = Auth::user();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'template_id' => ['required', 'uuid', 'exists:letter_templates,id'],
            'classification' => ['required', 'in:INTERNAL,TERBATAS,RAHASIA'],
            'planned_date' => ['required', 'date'],
            'expected_revision_no' => ['required', 'integer'],
            'change_summary' => ['nullable', 'string', 'max:500'],
            'variables' => ['nullable', 'array'],
        ]);

        $revision = $this->draftService->reviseDraft(
            $user,
            $letter,
            $validated,
            (int) $validated['expected_revision_no']
        );

        return redirect()->route('letters.show', $letter)
            ->with('success', "Revisi v{$revision->version} berhasil disimpan.");
    }

    /**
     * Menampilkan pratinjau surat dengan watermark wajib "DRAF — BELUM RESMI".
     */
    public function preview(Letter $letter, Request $request): View
    {
        $this->authorize('preview', $letter);
        $user = Auth::user();

        $version = $request->query('version', $letter->current_revision_no);
        $revision = $letter->revisions()->where('version', $version)->firstOrFail();

        $this->draftService->recordPreviewAudit($user, $letter, (int) $version);

        return view('letters.preview', compact('letter', 'revision'));
    }

    /**
     * Mengajukan draf surat untuk ditelaah oleh reviewer.
     */
    public function submit(Request $request, Letter $letter): RedirectResponse
    {
        $this->authorize('submit', $letter);
        $user = Auth::user();

        $validated = $request->validate([
            'submission_notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $this->approvalService->submit($user, $letter, $validated);

        return redirect()->route('letters.show', $letter)
            ->with('success', 'Draf surat berhasil diajukan untuk pemeriksaan reviewer.');
    }

    /**
     * Menampilkan riwayat status, linimasa revisi, dan keputusan reviewer secara komprehensif.
     */
    public function history(Letter $letter): View
    {
        $this->authorize('view', $letter);

        $letter->load([
            'revisions.creator',
            'statusHistory.actor',
            'approvalRequests.submitter',
            'approvalRequests.claimedBy',
            'approvalRequests.steps.reviewer',
            'approvalRequests.steps.mandate',
        ]);

        return view('letters.history', compact('letter'));
    }
}
