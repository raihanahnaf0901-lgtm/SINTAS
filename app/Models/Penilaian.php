<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Penilaian extends Model
{
    protected $table = 'penilaian';

    protected $fillable = ['siswa_id', 'tugas_id', 'ujian_id', 'nilai', 'dinilai_oleh', 'catatan', 'dinilai_at'];

    protected function casts(): array
    {
        return ['nilai' => 'decimal:2', 'dinilai_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (Penilaian $nilai): void {
            if (($nilai->tugas_id === null) === ($nilai->ujian_id === null) || $nilai->nilai < 0 || $nilai->nilai > 100) {
                throw ValidationException::withMessages(['nilai' => 'Nilai 0-100 harus terkait tepat satu tugas atau ujian.']);
            }
        });
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(Tugas::class);
    }

    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class);
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'dinilai_oleh');
    }
}
