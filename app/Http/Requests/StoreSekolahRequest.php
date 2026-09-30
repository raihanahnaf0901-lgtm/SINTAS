<?php

namespace App\Http\Requests;

use App\Models\Sekolah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Sekolah::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'nama_sekolah' => ['required', 'string', 'max:255'],
            'npsn' => ['required', 'digits:8', Rule::unique('sekolah', 'npsn')],
            'alamat' => ['nullable', 'string', 'max:2000'],
            'plan_code' => ['required', 'string', Rule::in(array_keys(config('school-subscriptions.plans', [])))],
        ];
    }

    public function messages(): array
    {
        return [
            'npsn.unique' => 'Sekolah dengan NPSN ini sudah didaftarkan. Mintalah kode sekolah kepada adminnya.',
            'npsn.digits' => 'NPSN harus terdiri dari 8 angka.',
            'plan_code.in' => 'Paket langganan belum tersedia atau tidak valid.',
        ];
    }
}
