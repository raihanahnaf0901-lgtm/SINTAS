<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiRekapManual extends Model
{
    protected $table = 'nilai_rekap_manual';

    protected $fillable = ['komponen_rekap_id', 'siswa_id', 'nilai', 'dinilai_oleh', 'catatan', 'dinilai_at'];

    protected function casts(): array
    {
        return ['nilai' => 'decimal:2', 'dinilai_at' => 'datetime'];
    }

    public function komponen(): BelongsTo
    {
        return $this->belongsTo(KomponenRekap::class, 'komponen_rekap_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'dinilai_oleh');
    }
}
