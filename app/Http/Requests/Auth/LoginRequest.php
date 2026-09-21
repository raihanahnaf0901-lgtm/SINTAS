<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return ['role' => ['required', 'in:siswa,guru'], 'email' => ['required', 'email'], 'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean']];
    }

    public function authenticate(): void
    {
        $key = 'account-login:'.$this->input('email').'|'.$this->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan login. Tunggu '.RateLimiter::availableIn($key).' detik.']);
        }
        if (! Auth::attempt(['email' => $this->input('email'), 'password' => $this->input('password'),
            'role' => $this->input('role'), 'status' => 'aktif',
            fn ($query) => $query->whereHas($this->input('role') === 'guru' ? 'guru' : 'siswa')], $this->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email, password, atau jenis akun tidak cocok. Akun baru harus menyelesaikan verifikasi pendaftaran terlebih dahulu.']);
        }
        RateLimiter::clear($key);
    }
}
