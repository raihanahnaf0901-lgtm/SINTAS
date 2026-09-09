<?php

namespace App\Services;

use App\Models\KonfigurasiRekap;
use App\Models\Penilaian;
use App\Models\Siswa;
use Illuminate\Support\Collection;

class RekapNilai
{
    public function calculate(KonfigurasiRekap $config, Collection $students): Collection
    {
        $studentIds = $students->pluck('id');
        $kelas = $config->kelasMapel;
        $targets = [
            'tugas' => $kelas->tugas()->pluck('id'),
            'UH' => $kelas->ujian()->where('jenis_ujian', 'UH')->pluck('id'),
            'US' => $kelas->ujian()->where('jenis_ujian', 'US')->pluck('id'),
        ];
        $grades = Penilaian::query()->whereIn('siswa_id', $studentIds)->where(fn ($q) => $q
            ->whereIn('tugas_id', $targets['tugas'])->orWhereIn('ujian_id', $targets['UH']->merge($targets['US'])))
            ->get()->groupBy('siswa_id');
        $config->load(['komponen.nilaiManual' => fn ($q) => $q->whereIn('siswa_id', $studentIds)]);

        return $students->map(function (Siswa $siswa) use ($config, $targets, $grades): array {
            $parts = $config->komponen->map(function ($part) use ($siswa, $targets, $grades): array {
                $record = null;
                if ($part->metode === 'manual') {
                    $record = $part->nilaiManual->firstWhere('siswa_id', $siswa->id);
                    $value = $record ? (float) $record->nilai : null;
                    $complete = $record !== null;
                } else {
                    $key = $part->jenis === 'tugas' ? 'tugas_id' : 'ujian_id';
                    $selected = ($grades->get($siswa->id) ?? collect())->whereIn($key, $targets[$part->jenis]);
                    $value = $selected->isEmpty() ? null : (float) $selected->avg('nilai');
                    $complete = $targets[$part->jenis]->isNotEmpty() && $selected->count() === $targets[$part->jenis]->count();
                }

                return ['id' => $part->id, 'jenis' => $part->jenis, 'metode' => $part->metode,
                    'bobot' => (float) $part->bobot, 'nilai' => $value === null ? null : round($value, 2), 'catatan' => $record?->catatan,
                    'kontribusi' => $value === null ? null : $value * (float) $part->bobot / 100,
                    'lengkap' => (float) $part->bobot === 0.0 || $complete];
            });
            $complete = $parts->isNotEmpty() && $parts->every(fn ($part) => $part['lengkap']);

            return ['siswa_id' => $siswa->id, 'nama_lengkap' => $siswa->nama_lengkap, 'nis' => $siswa->nis,
                'komponen' => $parts, 'lengkap' => $complete,
                'nilai_akhir' => $complete ? round($parts->sum('kontribusi'), 2) : null];
        });
    }
}
