<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentArchiveController;
use App\Http\Controllers\LegacyRegisterController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\MandateController;
use App\Http\Controllers\NumberingPolicyController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\TemplateController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes: P4I Office v1.0 (Sprint M1, M2, M3A)
|--------------------------------------------------------------------------
|
| Rute otentikasi, dashboard, persuratan draf, riwayat revisi,
| pratinjau watermark, templat dinamis, antrean telaah, mandat pejabat,
| kebijakan penomoran simulasi, staging register historis, buku agenda ganda,
| dan laporan kesiapan (readiness persuratan).
|
*/

// Rute Tamu (Guest)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Rute Terproteksi (Authenticated & Active User)
Route::middleware(['auth', 'active.user'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Modul Persuratan Draf (Sprint M1 & M2)
    Route::get('/letters', [LetterController::class, 'index'])->name('letters.index');
    Route::get('/letters/create', [LetterController::class, 'create'])->name('letters.create');
    Route::post('/letters', [LetterController::class, 'store'])->name('letters.store');
    Route::get('/letters/{letter}', [LetterController::class, 'show'])->name('letters.show');
    Route::get('/letters/{letter}/edit', [LetterController::class, 'edit'])->name('letters.edit');
    Route::put('/letters/{letter}', [LetterController::class, 'update'])->name('letters.update');
    Route::get('/letters/{letter}/preview', [LetterController::class, 'preview'])->name('letters.preview');
    Route::post('/letters/{letter}/submit', [LetterController::class, 'submit'])->name('letters.submit');
    Route::get('/letters/{letter}/history', [LetterController::class, 'history'])->name('letters.history');

    // Manajemen Templat Surat
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::patch('/templates/{template}/toggle', [TemplateController::class, 'toggleActive'])->name('templates.toggle');

    // Modul Alur Persetujuan & Antrean Pemeriksa (Sprint M2)
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('/approvals/{letter}', [ApprovalController::class, 'show'])->name('approvals.show');
    Route::post('/approvals/{letter}/claim', [ApprovalController::class, 'claim'])->name('approvals.claim');
    Route::post('/approvals/{letter}/decision', [ApprovalController::class, 'decision'])->name('approvals.decision');

    // Modul Mandat Pejabat
    Route::get('/mandates', [MandateController::class, 'index'])->name('mandates.index');

    // Modul Fondasi Penomoran & Pratinjau Simulasi (Sprint M3A)
    Route::get('/numbering/policies', [NumberingPolicyController::class, 'index'])->name('numbering.policies.index');
    Route::post('/numbering/policies', [NumberingPolicyController::class, 'store'])->name('numbering.policies.store');
    Route::post('/numbering/preview', [NumberingPolicyController::class, 'preview'])->name('numbering.preview');
    Route::post('/numbering/simulate', [NumberingPolicyController::class, 'simulate'])->name('numbering.simulate');

    // Modul Staging Register Historis (Sprint M3A & M3B)
    Route::get('/legacy-registers', [LegacyRegisterController::class, 'index'])->name('legacy.index');
    Route::get('/legacy-registers/create', [LegacyRegisterController::class, 'create'])->name('legacy.create');
    Route::post('/legacy-registers', [LegacyRegisterController::class, 'store'])->name('legacy.store');
    Route::get('/legacy-registers/{id}', [LegacyRegisterController::class, 'show'])->name('legacy.show');
    Route::post('/legacy-registers/{id}/verify', [LegacyRegisterController::class, 'verify'])->name('legacy.verify');
    Route::post('/legacy-registers/rows/{row}/reconcile', [LegacyRegisterController::class, 'reconcileRow'])->name('legacy.rows.reconcile');

    // Modul Buku Agenda Ganda (Sprint M3A)
    Route::get('/agenda/outgoing', [AgendaController::class, 'outgoing'])->name('agenda.outgoing');
    Route::get('/agenda/incoming', [AgendaController::class, 'incoming'])->name('agenda.incoming');
    Route::get('/agenda/incoming/create', [AgendaController::class, 'createIncoming'])->name('agenda.incoming.create');
    Route::post('/agenda/incoming', [AgendaController::class, 'storeIncoming'])->name('agenda.incoming.store');

    // Modul Laporan Kesiapan Persuratan Yayasan (Sprint M3A)
    Route::get('/readiness', [ReadinessController::class, 'index'])->name('readiness.index');

    // Modul Ekspor Naskah & Arsip Digital Privat (Sprint M4A)
    Route::post('/letters/{letter}/export', [DocumentArchiveController::class, 'export'])->name('letters.export');
    Route::get('/archives', [DocumentArchiveController::class, 'index'])->name('archives.index');
    Route::get('/archives/admin', [DocumentArchiveController::class, 'admin'])->name('archives.admin');
    Route::post('/archives/admin/verify', [DocumentArchiveController::class, 'verifyIntegrity'])->name('archives.admin.verify');
    Route::get('/archives/{export}/download', [DocumentArchiveController::class, 'download'])->name('archives.download');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Rute Helper Khusus Lingkungan Lokal Pengujian Browser (Dev Only)
if (app()->environment('local')) {
    Route::get('/dev-login/{email}', function (string $email) {
        $user = User::where('email', $email)->firstOrFail();
        Auth::login($user);

        return redirect(request('redirect', '/dashboard'));
    })->name('dev.login');
}
