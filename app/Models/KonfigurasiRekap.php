<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KonfigurasiRekap extends Model
{
    protected $table = 'konfigurasi_rekap';

    protected $fillable = ['kelas_mapel_id', 'guru_id', 'nama_konfigurasi', 'status'];

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function komponen(): HasMany
    {
        return $this->hasMany(KomponenRekap::class);
    }
}
