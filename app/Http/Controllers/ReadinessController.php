<?php

namespace App\Http\Controllers;

use App\Services\ReadinessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReadinessController extends Controller
{
    public function __construct(
        protected ReadinessService $readinessService
    ) {}

    /**
     * Menampilkan dashboard Laporan Kesiapan Persuratan Yayasan (NR-16).
     * Melaporkan status secara objektif (BLOCKED) bila SK atau rekonsiliasi belum lengkap.
     */
    public function index(): View
    {
        $user = Auth::user();
        $report = $this->readinessService->evaluateReadiness($user->organization);

        return view('readiness.index', compact('report'));
    }
}
