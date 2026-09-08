<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifySiswaLoginCodeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticatedSiswaSessionController extends Controller
{
    public function store(VerifySiswaLoginCodeRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $throttleKey = $this->throttleKey($email, $request->ip());

        $this->ensureIsNotRateLimited($throttleKey);
        RateLimiter::hit($throttleKey, max(1, (int) config('siswa_login.verify_decay_seconds')));

        $user = User::query()
            ->where('email', $email)
            ->where('role', 'siswa')
            ->first();

        if (! $user || ! $user->siswa()->exists()) {
            $this->throwInvalidCode();
        }

        $code = $request->string('code')->toString();
        $maxCodeAttempts = max(1, (int) config('siswa_login.code_max_attempts'));

        $verified = DB::transaction(function () use ($user, $code, $maxCodeAttempts): bool {
            $loginCode = $user->siswaLoginCodes()
                ->whereNull('used_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $loginCode) {
                return false;
            }

            if ($loginCode->expires_at->isPast() || $loginCode->attempts >= $maxCodeAttempts) {
                $loginCode->update(['used_at' => now()]);

                return false;
            }

            if (! Hash::check($code, $loginCode->code_hash)) {
                $attempts = $loginCode->attempts + 1;
                $loginCode->update([
                    'attempts' => $attempts,
                    'used_at' => $attempts >= $maxCodeAttempts ? now() : null,
                ]);

                return false;
            }

            $loginCode->update(['used_at' => now()]);
            $user->siswaLoginCodes()->whereNull('used_at')->update(['used_at' => now()]);

            return true;
        });

        if (! $verified) {
            $this->throwInvalidCode();
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        RateLimiter::clear($throttleKey);

        return response()->json([
            'message' => 'Verifikasi berhasil. Anda telah masuk sebagai siswa.',
            'redirect' => $request->session()->pull('url.intended', route('dashboard', absolute: false)),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(string $key): void
    {
        $maxAttempts = max(1, (int) config('siswa_login.verify_max_attempts'));

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'code' => "Terlalu banyak percobaan verifikasi. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function throwInvalidCode(): never
    {
        throw ValidationException::withMessages([
            'code' => 'Kode verifikasi salah, kedaluwarsa, atau sudah digunakan.',
        ]);
    }

    private function throttleKey(string $email, ?string $ipAddress): string
    {
        return 'siswa-login-verify:'.Str::transliterate(Str::lower($email.'|'.$ipAddress));
    }
}
