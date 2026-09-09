<?php

namespace App\Services;

use App\Models\OtpVerification;
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
        $key = 'otp-send:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, config('otp.send_limit'))) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak permintaan kode. Silakan tunggu beberapa menit.']);
        }
        RateLimiter::hit($key, config('otp.send_decay_seconds'));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = max(1, (int) config('otp.expires_minutes'));
        $record = DB::transaction(function () use ($user, $purpose, $code, $minutes): OtpVerification {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $validStatus = $purpose === 'register' ? 'pending' : 'aktif';
            if ($locked->status !== $validStatus || ! in_array($locked->role, ['siswa', 'guru'], true)
                || $locked->email !== $user->email || $locked->password !== $user->password) {
                throw ValidationException::withMessages(['email' => 'Akun berubah atau tidak aktif. Ulangi permintaan.']);
            }
            $locked->otpVerifications()->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now()]);

            return $locked->otpVerifications()->create([
                'code_hash' => Hash::make($code), 'purpose' => $purpose,
                'expires_at' => now()->addMinutes($minutes), 'attempts' => 0,
            ]);
        });
        try {
            $user->notify(new KodeVerifikasiAkun($code, $purpose, $minutes));
        } catch (\Throwable $exception) {
            $record->update(['used_at' => now()]);
            report($exception);
            throw ValidationException::withMessages(['email' => 'Kode gagal dikirim. Coba lagi setelah konfigurasi email diperiksa.']);
        }
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
