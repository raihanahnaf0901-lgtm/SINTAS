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
        $key = 'teacher-login:'.$this->input('email').'|'.$this->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan login. Tunggu sebentar.']);
        }
        RateLimiter::hit($key, 60);
        if ($this->input('role') !== 'guru') {
            throw ValidationException::withMessages(['role' => 'Login siswa memerlukan kode verifikasi email.']);
        }
        if (! Auth::attempt(['email' => $this->input('email'), 'password' => $this->input('password'),
            'role' => 'guru', 'status' => 'aktif',
            fn ($query) => $query->whereHas('guru')], $this->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Email atau password guru tidak cocok, atau akun belum aktif.']);
        }
        RateLimiter::clear($key);
    }
}
