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
        return $this->registerAccount($request, $otp, 'siswa');
    }

    public function registerGuru(Request $request, OtpService $otp): JsonResponse
    {
        return $this->registerAccount($request, $otp, 'guru');
    }

    private function registerAccount(Request $request, OtpService $otp, string $role): JsonResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'min:3', 'max:100', 'alpha_dash'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
            ...($role === 'guru' ? [
                'nip' => ['required', 'string', 'max:30'],
                'gelar' => ['nullable', 'string', 'max:50'],
                'jenis_guru' => ['required', Rule::in(['guru_mapel', 'guru_piket'])],
            ] : []),
        ]);
        try {
            DB::transaction(function () use ($request, $data, $role, $otp): User {
                $existing = User::query()->where('email', $data['email'])->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->role !== $role || $existing->status !== 'pending' || ! Hash::check($data['password'], $existing->password)) {
                        throw ValidationException::withMessages(['email' => 'Email sudah terdaftar. Gunakan login atau reset password.']);
                    }

                    if ($role === 'guru') {
                        $request->validate(['nip' => [Rule::unique('guru')->ignore($existing->guru?->id)]],
                            ['nip.unique' => 'NIP sudah digunakan oleh akun guru lain.']);
                        $existing->update(['name' => $data['name']]);
                        $existing->guru()->updateOrCreate(['user_id' => $existing->id], [
                            'nama_lengkap' => $data['name'], 'nip' => $data['nip'],
                            'gelar' => $data['gelar'] ?? null, 'jenis_guru' => $data['jenis_guru'],
                        ]);
                    }

                    $otp->send($existing, 'register');

                    return $existing;
                }
                if (filled($data['username'] ?? null) && User::query()->where('username', $data['username'])->exists()) {
                    throw ValidationException::withMessages(['username' => 'Username sudah digunakan.']);
                }
                if ($role === 'guru') {
                    $request->validate(['nip' => [Rule::unique('guru')]],
                        ['nip.unique' => 'NIP sudah digunakan oleh akun guru lain.']);
                }
                $user = User::query()->create([
                    'name' => $data['name'], 'username' => $data['username'] ?? null,
                    'email' => $data['email'], 'password' => $data['password'], 'role' => $role, 'status' => 'pending',
                ]);
                if ($role === 'guru') {
                    $user->guru()->create(['nama_lengkap' => $data['name'], 'nip' => $data['nip'],
                        'gelar' => $data['gelar'] ?? null, 'jenis_guru' => $data['jenis_guru']]);
                } else {
                    $user->siswa()->create(['nama_lengkap' => $data['name']]);
                }

                $otp->send($user, 'register');

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => $role === 'guru'
                ? 'Email, username, atau NIP sudah digunakan. Periksa kembali data pendaftaran.'
                : 'Email atau username sudah digunakan. Ulangi dengan data lain.']);
        }

        return $this->sent(true);
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

    public function verifyGuruRegister(Request $request, OtpService $otp): JsonResponse
    {
        return $this->verify($request, $otp, 'register', 'guru');
    }

    private function verify(Request $request, OtpService $otp, string $purpose, string $role = 'siswa'): JsonResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'email' => ['required', 'email'], 'code' => ['required', 'digits:6'], 'remember' => ['sometimes', 'boolean'],
        ]);
        $user = User::query()->where('email', $data['email'])->where('role', $role)->first();
        if (! $user || ! ($role === 'guru' ? $user->guru : $user->siswa) || ! $otp->consume($user, $purpose, $data['code'], function (User $locked): void {
            $locked->forceFill(['status' => 'aktif', 'email_verified_at' => $locked->email_verified_at ?? now()])->save();
        })) {
            $this->invalidCode();
        }
        Auth::login($user->fresh(), $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json(['message' => 'Verifikasi berhasil.', 'redirect' => route('dashboard', absolute: false),
            'needs_profile' => $role === 'siswa' && ! filled($user->siswa->nis)]);
    }

    public function resetCode(Request $request, OtpService $otp): JsonResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', $data['email'])->where('status', 'aktif')->whereIn('role', ['siswa', 'guru'])->first();
        if ($user) {
            $otp->send($user, 'reset_password');
        }

        return response()->json(['message' => 'Jika akun terdaftar dan aktif, kode reset password akan dikirim.',
            'resend_after_seconds' => config('otp.resend_after_seconds')], 202);
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
            'kelas_siswa' => ['sometimes', 'nullable', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'min:3', 'max:100', 'alpha_dash', Rule::unique('users')->ignore($request->user())],
        ], [
            'kelas_siswa.string' => 'Kelas siswa harus berupa teks.',
            'kelas_siswa.max' => 'Kelas siswa maksimal 255 karakter.',
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

    private function sent(bool $registering = false): JsonResponse
    {
        return response()->json(['message' => ($registering ? 'Pendaftaran belum selesai. Akun baru aktif setelah kode OTP diverifikasi. ' : '').'Kode verifikasi telah diserahkan ke server email. Periksa kotak masuk atau folder spam dan gunakan kode terbaru.',
            'resend_after_seconds' => config('otp.resend_after_seconds'),
            'expires_in_minutes' => max(1, (int) config('otp.expires_minutes'))], 202);
    }

    private function invalidCode(): never
    {
        throw ValidationException::withMessages(['code' => 'Kode verifikasi salah, kedaluwarsa, atau sudah digunakan.']);
    }
}
