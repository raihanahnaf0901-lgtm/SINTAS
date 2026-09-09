<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestSiswaRegistrationCodeRequest;
use App\Models\SiswaRegistrationCode;
use App\Notifications\KodeVerifikasiRegistrasiSiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SiswaRegistrationCodeController extends Controller
{
    public function store(RequestSiswaRegistrationCodeRequest $request): JsonResponse|RedirectResponse
    {
        $email = $request->string('email')->toString();
        $throttleKey = $this->throttleKey($email, $request->ip());

        $this->ensureIsNotRateLimited($throttleKey);
        RateLimiter::hit($throttleKey, max(1, (int) config('siswa_registration.send_decay_seconds')));

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresInMinutes = max(1, (int) config('siswa_registration.code_expires_minutes'));

        $registrationCode = DB::transaction(function () use ($request, $email, $code, $expiresInMinutes): SiswaRegistrationCode {
            SiswaRegistrationCode::query()
                ->where('email', $email)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return SiswaRegistrationCode::query()->create([
                'email' => $email,
                'name' => $request->string('name')->toString(),
                'password_hash' => Hash::make($request->string('password')->toString()),
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes($expiresInMinutes),
            ]);
        });

        try {
            Notification::route('mail', $email)
                ->notify(new KodeVerifikasiRegistrasiSiswa($code, $expiresInMinutes));
        } catch (Throwable $exception) {
            $registrationCode->delete();
            report($exception);

            throw ValidationException::withMessages([
                'email' => 'Kode verifikasi gagal dikirim. Periksa konfigurasi Resend lalu coba lagi.',
            ]);
        }

        $response = [
            'message' => 'Kode verifikasi pendaftaran telah dikirim ke email belajar.id Anda.',
            'expires_in_minutes' => $expiresInMinutes,
        ];

        if ($request->expectsJson()) {
            return response()->json($response, 202);
        }

        return back()->with('status', $response['message']);
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(string $key): void
    {
        $maxAttempts = max(1, (int) config('siswa_registration.send_max_attempts'));

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
        return 'siswa-registration-send:'.Str::transliterate(Str::lower($email.'|'.$ipAddress));
    }
}
