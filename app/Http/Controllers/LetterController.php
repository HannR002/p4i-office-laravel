<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LetterController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $letters = Letter::where('organization_id', $user->organization_id)
            ->with(['letterType', 'author'])
            ->latest()
            ->paginate(15);

        return view('letters.index', compact('letters', 'user'));
    }

    public function show(Letter $letter): View
    {
        // Kerahasiaan (CONFIDENTIAL isolation)
        if ($letter->classification === 'CONFIDENTIAL') {
            $user = Auth::user();
            if ($user->id !== $letter->author_id && !$user->isSystemAdmin()) {
                abort(403, 'Akses ditolak: Surat ini bersifat RAHASIA (CONFIDENTIAL).');
            }
        }

        $letter->load(['revisions', 'approvalRequests.steps', 'statusHistory.actor']);

        return view('letters.show', compact('letter'));
    }
}
