<?php

namespace App\Http\Requests;

use App\Models\KelasMapel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreKelasMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', KelasMapel::class) ?? false;
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
            'mapel_id' => ['required', 'integer', 'exists:mapel,id'],
            'nama_kelas_mapel' => ['required', 'string', 'max:255'],
            'kode_kelas' => ['nullable', 'string', 'min:6', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('kelas_mapel')],
            'deskripsi' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
