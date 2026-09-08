<?php

namespace App\Models;

use Database\Factories\MapelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mapel extends Model
{
    /** @use HasFactory<MapelFactory> */
    use HasFactory;

    protected $fillable = [
        'kode_mapel',
        'nama_mapel',
        'deskripsi',
        'kelompok',
    ];

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function ruangMapels(): HasMany
    {
        return $this->hasMany(RuangMapel::class);
    }
}
