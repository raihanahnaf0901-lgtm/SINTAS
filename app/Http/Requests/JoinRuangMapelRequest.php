<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class JoinRuangMapelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'siswa'
            && $this->user()->siswa()->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kode' => [
                'required',
                'string',
                'max:20',
                Rule::exists('ruang_mapels', 'kode')->where('aktif', true),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode' => Str::upper($this->string('kode')->trim()->toString()),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode ruang mata pelajaran wajib diisi.',
            'kode.exists' => 'Kode ruang tidak ditemukan atau sudah tidak aktif.',
        ];
    }
}
