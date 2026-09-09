<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class OtpAuthController extends Controller
{
    public function register(Request $request, OtpService $otp): JsonResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'min:3', 'max:100', 'alpha_dash'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        try {
            $user = DB::transaction(function () use ($data): User {
                $existing = User::query()->where('email', $data['email'])->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->role !== 'siswa' || $existing->status !== 'pending' || ! Hash::check($data['password'], $existing->password)) {
                        throw ValidationException::withMessages(['email' => 'Email sudah terdaftar. Gunakan login atau reset password.']);
                    }

                    return $existing;
                }
                if (filled($data['username'] ?? null) && User::query()->where('username', $data['username'])->exists()) {
                    throw ValidationException::withMessages(['username' => 'Username sudah digunakan.']);
                }
                $user = User::query()->create([
                    'name' => $data['name'], 'username' => $data['username'] ?? null,
                    'email' => $data['email'], 'password' => $data['password'], 'role' => 'siswa', 'status' => 'pending',
                ]);
                $user->siswa()->create(['nama_lengkap' => $data['name']]);

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'Email atau username sudah digunakan. Ulangi dengan data lain.']);
        }
        $otp->send($user, 'register');

        return $this->sent();
    }

    public function loginCode(Request $request, OtpService $otp): JsonResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string']]);
        $user = User::query()->where('email', $data['email'])->where('role', 'siswa')->where('status', 'aktif')->first();
        if (! $user || ! $user->siswa || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Email atau password siswa tidak cocok, atau akun belum aktif.']);
        }
        $otp->send($user, 'login');

        return $this->sent();
    }

    public function verifyRegister(Request $request, OtpService $otp): JsonResponse
    {
        return $this->verify($request, $otp, 'register');
    }

    public function verifyLogin(Request $request, OtpService $otp): JsonResponse
    {
        return $this->verify($request, $otp, 'login');
    }

    private function verify(Request $request, OtpService $otp, string $purpose): JsonResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'email' => ['required', 'email'], 'code' => ['required', 'digits:6'], 'remember' => ['sometimes', 'boolean'],
        ]);
        $user = User::query()->where('email', $data['email'])->where('role', 'siswa')->first();
        if (! $user || ! $user->siswa || ! $otp->consume($user, $purpose, $data['code'], function (User $locked): void {
            $locked->forceFill(['status' => 'aktif', 'email_verified_at' => $locked->email_verified_at ?? now()])->save();
        })) {
            $this->invalidCode();
        }
        Auth::login($user->fresh(), $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json(['message' => 'Verifikasi berhasil.', 'redirect' => route('dashboard', absolute: false),
            'needs_profile' => ! filled($user->siswa->nis)]);
    }

    public function resetCode(Request $request, OtpService $otp): JsonResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', $data['email'])->where('status', 'aktif')->whereIn('role', ['siswa', 'guru'])->first();
        if ($user) {
            $otp->send($user, 'reset_password');
        }

        return response()->json(['message' => 'Jika akun terdaftar dan aktif, kode reset password akan dikirim.'], 202);
    }

    public function resetPassword(Request $request, OtpService $otp): JsonResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)]]);
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! $otp->consume($user, 'reset_password', $data['code'], function (User $locked) use ($data): void {
            $locked->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
            $locked->otpVerifications()->whereNull('used_at')->update(['used_at' => now()]);
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $locked->id)->delete();
            }
            event(new PasswordReset($locked));
        })) {
            $this->invalidCode();
        }

        return response()->json(['message' => 'Password berhasil diubah. Silakan login kembali.']);
    }

    public function completeProfile(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'siswa' && $request->user()->siswa, 403);
        $siswa = $request->user()->siswa;
        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nis' => ['required', 'string', 'max:20', Rule::unique('siswa')->ignore($siswa)],
            'nisn' => ['sometimes', 'nullable', 'digits:10', Rule::unique('siswa')->ignore($siswa)],
            'kelas_id' => ['sometimes', 'nullable', 'integer', Rule::exists('kelas', 'id')->where('status', 'aktif')],
            'username' => ['sometimes', 'string', 'min:3', 'max:100', 'alpha_dash', Rule::unique('users')->ignore($request->user())],
        ]);
        DB::transaction(function () use ($request, $siswa, $data): void {
            $siswa->update(collect($data)->except('username')->all());
            $request->user()->update(['name' => $data['nama_lengkap'],
                'username' => $data['username'] ?? $request->user()->username]);
        });

        return response()->json(['data' => $request->user()->fresh()->load('siswa.kelas')]);
    }

    private function normalizeEmail(Request $request): void
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
    }

    private function sent(): JsonResponse
    {
        return response()->json(['message' => 'Kode verifikasi telah dikirim ke email Anda.',
            'expires_in_minutes' => max(1, (int) config('otp.expires_minutes'))], 202);
    }

    private function invalidCode(): never
    {
        throw ValidationException::withMessages(['code' => 'Kode verifikasi salah, kedaluwarsa, atau sudah digunakan.']);
    }
}
