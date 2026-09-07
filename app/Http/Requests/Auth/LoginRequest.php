<?php

namespace App\Http\Requests\Auth;

use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:admin,siswa,guru'],
            'email' => ['required_if:role,admin', 'nullable', 'email'],
            'password' => ['required_if:role,admin', 'nullable', 'string'],
            'nama_lengkap' => ['required_if:role,siswa', 'nullable', 'string', 'max:100'],
            'nis' => ['required_if:role,siswa', 'nullable', 'string', 'max:20'],
            'kelas_id' => ['required_if:role,siswa', 'nullable', 'integer', 'exists:kelas,id'],
            'nama' => ['required_if:role,guru', 'nullable', 'string', 'max:100'],
            'gelar' => ['required_if:role,guru', 'nullable', 'string', 'max:50'],
            'nip' => ['required_if:role,guru', 'nullable', 'string', 'max:30'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = match ($this->string('role')->toString()) {
            'admin' => Auth::attempt([
                'email' => $this->string('email')->toString(),
                'password' => $this->string('password')->toString(),
                'role' => 'admin',
            ], $this->boolean('remember')),
            'siswa' => $this->authenticateSiswa(),
            'guru' => $this->authenticateGuru(),
        };

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'role' => 'Data login tidak cocok.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower(implode('|', [
            $this->string('role')->toString(),
            $this->string('email')->toString(),
            $this->string('nis')->toString(),
            $this->string('nip')->toString(),
            $this->ip(),
        ])));
    }

    private function authenticateSiswa(): bool
    {
        $siswa = Siswa::query()
            ->with('user')
            ->where('nama_lengkap', $this->string('nama_lengkap')->toString())
            ->where('nis', $this->string('nis')->toString())
            ->where('kelas_id', $this->integer('kelas_id'))
            ->first();

        if ($siswa?->user?->role !== 'siswa') {
            return false;
        }

        Auth::login($siswa->user);

        return true;
    }

    private function authenticateGuru(): bool
    {
        $guru = Guru::query()
            ->with('user')
            ->where('nama', $this->string('nama')->toString())
            ->where('gelar', $this->string('gelar')->toString())
            ->where('nip', $this->string('nip')->toString())
            ->first();

        if ($guru?->user?->role !== 'guru') {
            return false;
        }

        Auth::login($guru->user);

        return true;
    }
}
