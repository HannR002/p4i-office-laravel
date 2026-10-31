<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\MandateController;
use App\Http\Controllers\TemplateController;
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

    // Modul Persuratan Draf (Sprint M1)
    Route::get('/letters', [LetterController::class, 'index'])->name('letters.index');
    Route::get('/letters/create', [LetterController::class, 'create'])->name('letters.create');
    Route::post('/letters', [LetterController::class, 'store'])->name('letters.store');
    Route::get('/letters/{letter}', [LetterController::class, 'show'])->name('letters.show');
    Route::get('/letters/{letter}/edit', [LetterController::class, 'edit'])->name('letters.edit');
    Route::put('/letters/{letter}', [LetterController::class, 'update'])->name('letters.update');
    Route::get('/letters/{letter}/preview', [LetterController::class, 'preview'])->name('letters.preview');

    // Manajemen Templat Surat
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::patch('/templates/{template}/toggle', [TemplateController::class, 'toggleActive'])->name('templates.toggle');

    // Modul Antrean Pemeriksaan (Reviewer / Pengurus)
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');

    // Modul Mandat Pejabat
    Route::get('/mandates', [MandateController::class, 'index'])->name('mandates.index');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
