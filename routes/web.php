<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\MandateController;
use App\Http\Controllers\TemplateController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes: P4I Office v1.0 (Sprint M1)
|--------------------------------------------------------------------------
|
| Rute otentikasi, dashboard, persuratan draf, riwayat revisi,
| pratinjau watermark, templat dinamis, antrean telaah, dan mandat pejabat.
| Seluruh rute dilindungi oleh middleware active.user untuk revokasi akun seketika.
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
