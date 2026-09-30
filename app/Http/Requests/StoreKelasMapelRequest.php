<?php

namespace App\Http\Requests;

use App\Services\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreKelasMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user?->status !== 'aktif' || $user->role !== 'guru' || $user->guru === null) {
            return false;
        }
        $school = app(SchoolContext::class)->school($this);

        return $school !== null
            ? $user->isSchoolAdmin($school) || $user->guru->jenisDiSekolah($school->id) === 'guru_mapel'
            : $user->guru->jenis_guru === 'guru_mapel' || $user->isSchoolAdmin();
    }

    public function rules(): array
    {
        return [
            'sekolah_id' => ['nullable', 'integer', 'exists:sekolah,id'],
            'mapel_id' => ['nullable', 'integer', 'exists:mapel,id'],
            'nama_kelas_mapel' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                if ($this->filled('sekolah_id')) {
                    abort_unless($this->user()->guru->keanggotaanSekolah()
                        ->where('sekolah_id', $this->integer('sekolah_id'))->exists(), 403);
                }
                $school = app(SchoolContext::class)->school($this);
                if ($school === null) {
                    $validator->errors()->add('sekolah', 'Bergabung dan tunggu persetujuan sekolah sebelum membuat kelas.');
                } elseif (! $school->hasActiveSubscription()) {
                    $validator->errors()->add('sekolah', 'Langganan sekolah harus aktif sebelum membuat kelas baru.');
                }
            },
        ];
    }
}
