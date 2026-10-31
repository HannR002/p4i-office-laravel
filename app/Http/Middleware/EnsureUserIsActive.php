<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Memastikan pengguna yang terotentikasi berstatus ACTIVE.
     * Jika akun diubah menjadi DISABLED saat sesi berjalan, sesi langsung dibatalkan (real-time revocation).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user()->fresh();
            if (! $user || $user->status !== 'ACTIVE') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Akun Anda dinonaktifkan (DISABLED). Silakan hubungi administrator sistem Yayasan P4I.',
                ]);
            }
        }

        return $next($request);
    }
}
