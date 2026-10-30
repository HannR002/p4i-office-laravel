<?php

namespace App\Http\Controllers;

use App\Models\SignatoryMandate;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MandateController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $mandates = SignatoryMandate::where('organization_id', $user->organization_id)
            ->with(['user', 'letterType', 'unit'])
            ->latest()
            ->get();

        return view('mandates.index', compact('mandates', 'user'));
    }
}
