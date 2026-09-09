<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateKelasMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('kelasMapel')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('kode_kelas')) {
            $this->merge(['kode_kelas' => Str::upper(trim($this->input('kode_kelas')))]);
        }
    }

    public function rules(): array
    {
        return [
            'nama_kelas_mapel' => ['sometimes', 'required', 'string', 'max:255'],
            'kode_kelas' => ['sometimes', 'required', 'string', 'min:6', 'max:32', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('kelas_mapel')->ignore($this->route('kelasMapel'))],
            'deskripsi' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(['aktif', 'arsip'])],
            'regenerate_invite' => ['sometimes', 'boolean'],
        ];
    }
}
