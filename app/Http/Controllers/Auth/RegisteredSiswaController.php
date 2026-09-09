<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifySiswaRegistrationCodeRequest;
use App\Models\Siswa;
use App\Models\SiswaRegistrationCode;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisteredSiswaController extends Controller
{
    public function store(VerifySiswaRegistrationCodeRequest $request): JsonResponse|RedirectResponse
    {
        $email = $request->string('email')->toString();
        $throttleKey = $this->throttleKey($email, $request->ip());

        $this->ensureIsNotRateLimited($throttleKey);
        RateLimiter::hit($throttleKey, max(1, (int) config('siswa_registration.verify_decay_seconds')));

        try {
            $user = $this->createVerifiedStudent($request);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => 'Email belajar.id atau NISN sudah terdaftar.',
            ]);
        }

        if (! $user) {
            $this->throwInvalidCode();
        }

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        RateLimiter::clear($throttleKey);

        $response = [
            'message' => 'Akun siswa berhasil dibuat dan email telah diverifikasi.',
            'redirect' => route('dashboard', absolute: false),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'nisn' => $user->siswa->nisn,
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json($response, 201);
        }

        return redirect()->intended($response['redirect']);
    }

    private function createVerifiedStudent(VerifySiswaRegistrationCodeRequest $request): ?User
    {
        $email = $request->string('email')->toString();
        $code = $request->string('code')->toString();
        $maxCodeAttempts = max(1, (int) config('siswa_registration.code_max_attempts'));

        return DB::transaction(function () use ($request, $email, $code, $maxCodeAttempts): ?User {
            $registrationCode = SiswaRegistrationCode::query()
                ->where('email', $email)
                ->whereNull('used_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $registrationCode) {
                return null;
            }

            if ($registrationCode->expires_at->isPast() || $registrationCode->attempts >= $maxCodeAttempts) {
                $registrationCode->update(['used_at' => now()]);

                return null;
            }

            if (! Hash::check($code, $registrationCode->code_hash)) {
                $attempts = $registrationCode->attempts + 1;
                $registrationCode->update([
                    'attempts' => $attempts,
                    'used_at' => $attempts >= $maxCodeAttempts ? now() : null,
                ]);

                return null;
            }

            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Email belajar.id ini sudah terdaftar.',
                ]);
            }

            $nisn = $request->string('nisn')->toString();

            if (Siswa::query()->where('nisn', $nisn)->exists()) {
                throw ValidationException::withMessages([
                    'nisn' => 'NISN ini sudah terdaftar.',
                ]);
            }

            $name = $request->filled('name')
                ? $request->string('name')->toString()
                : $registrationCode->name;
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $registrationCode->password_hash,
                'role' => 'siswa',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->siswa()->create([
                'nama_lengkap' => $name,
                'nisn' => $nisn,
                'status' => 'aktif',
            ]);

            SiswaRegistrationCode::query()->where('email', $email)->delete();

            return $user->load('siswa');
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(string $key): void
    {
        $maxAttempts = max(1, (int) config('siswa_registration.verify_max_attempts'));

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
        return 'siswa-registration-verify:'.Str::transliterate(Str::lower($email.'|'.$ipAddress));
    }
}
