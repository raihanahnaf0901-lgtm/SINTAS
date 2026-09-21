<?php

namespace App\Http\Requests;

use App\Models\KelasMapel;
use Illuminate\Foundation\Http\FormRequest;

class StoreKelasMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', KelasMapel::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'mapel_id' => ['nullable', 'integer', 'exists:mapel,id'],
            'nama_kelas_mapel' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
