<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestSiswaLoginCodeRequest;
use App\Models\SiswaLoginCode;
use App\Models\User;
use App\Notifications\KodeVerifikasiLoginSiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SiswaLoginCodeController extends Controller
{
    public function store(RequestSiswaLoginCodeRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $throttleKey = $this->throttleKey($email, $request->ip());

        $this->ensureIsNotRateLimited($throttleKey);
        RateLimiter::hit($throttleKey, max(1, (int) config('siswa_login.send_decay_seconds')));

        $credentialsAreValid = Auth::validate([
            'email' => $email,
            'password' => $request->string('password')->toString(),
            'role' => 'siswa',
        ]);
        $user = $credentialsAreValid
            ? User::query()->where('email', $email)->where('role', 'siswa')->first()
            : null;

        if (! $user || ! $user->siswa()->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password siswa tidak cocok.',
            ]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresInMinutes = max(1, (int) config('siswa_login.code_expires_minutes'));

        $loginCode = DB::transaction(function () use ($user, $code, $expiresInMinutes): SiswaLoginCode {
            $user->siswaLoginCodes()
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return $user->siswaLoginCodes()->create([
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes($expiresInMinutes),
            ]);
        });

        try {
            $user->notify(new KodeVerifikasiLoginSiswa($code, $expiresInMinutes));
        } catch (Throwable $exception) {
            $loginCode->delete();
            report($exception);

            throw ValidationException::withMessages([
                'email' => 'Kode verifikasi gagal dikirim. Periksa konfigurasi email lalu coba lagi.',
            ]);
        }

        return response()->json([
            'message' => 'Kode verifikasi telah dikirim ke email belajar.id Anda.',
            'expires_in_minutes' => $expiresInMinutes,
        ], 202);
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(string $key): void
    {
        $maxAttempts = max(1, (int) config('siswa_login.send_max_attempts'));

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak permintaan kode. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    private function throttleKey(string $email, ?string $ipAddress): string
    {
        return 'siswa-login-send:'.Str::transliterate(Str::lower($email.'|'.$ipAddress));
    }
}
