<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Letter;
use App\Models\SignatoryMandate;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $orgId = $user->organization_id;

        // Hitung statistik ringkas sesuai scope organisasi
        $myDraftsCount = Letter::where('organization_id', $orgId)
            ->where('author_id', $user->id)
            ->count();

        $pendingApprovalsCount = ApprovalRequest::where('organization_id', $orgId)
            ->whereIn('status', ['PENDING', 'IN_REVIEW'])
            ->count();

        $activeMandatesCount = SignatoryMandate::where('organization_id', $orgId)
            ->where('status', 'ACTIVE')
            ->count();

        $recentLetters = Letter::where('organization_id', $orgId)
            ->with(['letterType', 'author'])
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'user',
            'myDraftsCount',
            'pendingApprovalsCount',
            'activeMandatesCount',
            'recentLetters'
        ));
    }
}
