<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\LetterTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TemplateController extends Controller
{
    /**
     * Menampilkan katalog templat surat.
     */
    public function index(): View
    {
        $this->authorize('viewAny', LetterTemplate::class);
        $user = Auth::user();

        $templates = LetterTemplate::where('organization_id', $user->organization_id)
            ->with('letterType')
            ->orderBy('title')
            ->get();

        return view('templates.index', compact('templates', 'user'));
    }

    /**
     * Mengaktifkan atau menonaktifkan status templat surat (Khusus Administrator).
     */
    public function toggleActive(LetterTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);
        $user = Auth::user();

        $newStatus = ! $template->is_active;
        $template->update(['is_active' => $newStatus]);

        AuditEvent::create([
            'organization_id' => $user->organization_id,
            'actor_id' => $user->id,
            'event_type' => 'TEMPLATE_UPDATED',
            'entity_type' => 'LETTER_TEMPLATE',
            'entity_id' => $template->id,
            'event_metadata' => [
                'action' => 'TOGGLE_ACTIVE',
                'is_active' => $newStatus,
                'template_title' => $template->title,
                'version' => $template->version,
            ],
        ]);

        $statusLabel = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('templates.index')
            ->with('success', "Templat '{$template->title}' berhasil {$statusLabel}.");
    }
}
