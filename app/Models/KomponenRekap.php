<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KomponenRekap extends Model
{
    protected $table = 'komponen_rekap';

    protected $fillable = ['konfigurasi_rekap_id', 'jenis', 'bobot', 'metode'];

    protected function casts(): array
    {
        return ['bobot' => 'decimal:2'];
    }

    public function konfigurasi(): BelongsTo
    {
        return $this->belongsTo(KonfigurasiRekap::class, 'konfigurasi_rekap_id');
    }

    public function nilaiManual(): HasMany
    {
        return $this->hasMany(NilaiRekapManual::class);
    }
}
