<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\KodeVerifikasiAkun;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function send(User $user, string $purpose): void
    {
        $key = 'otp-resend:'.$user->id;
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = max(1, (int) config('otp.expires_minutes'));
        DB::transaction(function () use ($user, $purpose, $code, $minutes, $key): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $validStatus = $purpose === 'register' ? 'pending' : 'aktif';
            if ($locked->status !== $validStatus || ! in_array($locked->role, ['siswa', 'guru'], true)
                || $locked->email !== $user->email || $locked->password !== $user->password) {
                throw ValidationException::withMessages(['email' => 'Akun berubah atau tidak aktif. Ulangi permintaan.']);
            }
            if (RateLimiter::tooManyAttempts($key, 1)) {
                $seconds = RateLimiter::availableIn($key);
                throw ValidationException::withMessages(['email' => "Tunggu {$seconds} detik sebelum mengirim ulang kode."]);
            }
            $locked->otpVerifications()->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now()]);

            $record = $locked->otpVerifications()->create([
                'code_hash' => Hash::make($code), 'purpose' => $purpose,
                'expires_at' => now()->addMinutes($minutes), 'attempts' => 0,
            ]);
            try {
                $locked->notify(new KodeVerifikasiAkun($code, $purpose, $minutes));
            } catch (\Throwable $exception) {
                report($exception);
                throw ValidationException::withMessages(['email' => 'Email belum berhasil dikirim. Periksa koneksi internet server lalu coba lagi. Kode sebelumnya tetap berlaku selama belum kedaluwarsa.']);
            }
            $record->update(['expires_at' => now()->addMinutes($minutes)]);
            RateLimiter::hit($key, max(1, (int) config('otp.resend_after_seconds', 15)));
        });
    }

    public function consume(User $user, string $purpose, string $code, Closure $success): bool
    {
        return DB::transaction(function () use ($user, $purpose, $code, $success): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->email !== $user->email || $locked->status !== ($purpose === 'register' ? 'pending' : 'aktif')
                || ! in_array($locked->role, ['siswa', 'guru'], true)) {
                return false;
            }
            $record = $locked->otpVerifications()->where('purpose', $purpose)->whereNull('used_at')
                ->latest('id')->lockForUpdate()->first();
            if (! $record) {
                return false;
            }
            $limit = max(1, (int) config('otp.max_attempts'));
            if ($record->expires_at->lessThanOrEqualTo(now()) || $record->attempts >= $limit) {
                $record->update(['used_at' => now()]);

                return false;
            }
            if (! Hash::check($code, $record->code_hash)) {
                $record->update(['attempts' => $record->attempts + 1,
                    'used_at' => $record->attempts + 1 >= $limit ? now() : null]);

                return false;
            }
            $record->update(['used_at' => now()]);
            $success($locked);

            return true;
        });
    }
}
