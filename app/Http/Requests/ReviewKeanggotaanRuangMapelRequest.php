<?php

namespace App\Http\Requests;

use App\StatusKeanggotaanRuangMapel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewKeanggotaanRuangMapelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'guru'
            && $this->user()->guru()->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(StatusKeanggotaanRuangMapel::class)->only([
                    StatusKeanggotaanRuangMapel::Diterima,
                    StatusKeanggotaanRuangMapel::Ditolak,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Keputusan wajib dipilih.',
            'status.enum' => 'Keputusan hanya boleh diterima atau ditolak.',
        ];
    }
}
