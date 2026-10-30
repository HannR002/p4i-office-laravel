<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\MandateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes: P4I Office v1.0 (Sprint M0)
|--------------------------------------------------------------------------
|
| Rute otentikasi, dashboard, persuratan, antrean telaah, dan mandat pejabat.
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

    // Modul Surat Dinas
    Route::get('/letters', [LetterController::class, 'index'])->name('letters.index');
    Route::get('/letters/{letter}', [LetterController::class, 'show'])->name('letters.show');

    // Modul Antrean Pemeriksaan (Reviewer / Pengurus)
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');

    // Modul Mandat Pejabat
    Route::get('/mandates', [MandateController::class, 'index'])->name('mandates.index');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
