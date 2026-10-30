<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        // Hanya peran REVIEWER, PENGURUS_YAYASAN, atau SYSTEM_ADMIN yang memiliki akses ke antrean pemeriksaan
        if (!$user->hasRole('REVIEWER') && !$user->hasRole('PENGURUS_YAYASAN') && !$user->isSystemAdmin()) {
            abort(403, 'Akses ditolak: Hanya reviewer berwenang atau pengurus yayasan yang dapat mengakses antrean pemeriksaan.');
        }

        $pendingRequests = ApprovalRequest::where('organization_id', $user->organization_id)
            ->whereIn('status', ['PENDING', 'IN_REVIEW'])
            ->with(['letter.letterType', 'submitter'])
            ->orderBy('submitted_at', 'asc')
            ->get();

        return view('approvals.index', compact('pendingRequests', 'user'));
    }
}
