<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class VerifySiswaLoginCodeRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', 'ends_with:@belajar.id'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower($this->string('email')->trim()->toString()),
            'code' => $this->string('code')->trim()->toString(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email belajar.id wajib diisi.',
            'email.email' => 'Format email belajar.id tidak valid.',
            'email.ends_with' => 'Siswa wajib menggunakan email dengan domain @belajar.id.',
            'code.required' => 'Kode verifikasi wajib diisi.',
            'code.regex' => 'Kode verifikasi harus terdiri dari 6 angka.',
        ];
    }
}
