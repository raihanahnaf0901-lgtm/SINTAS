<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->status === 'aktif' && in_array($user->role, ['siswa', 'guru'], true), 403, 'Akun belum aktif atau tidak diizinkan.');
        abort_unless($user->email_verified_at !== null, 403, 'Verifikasi email diperlukan.');

        return $next($request);
    }
}
