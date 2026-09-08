<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guru extends Model
{
    use HasFactory;

    protected $table = 'gurus';

    protected $fillable = [
        'user_id',
        'nama',
        'gelar',
        'nip',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function ruangMapels(): HasMany
    {
        return $this->hasMany(RuangMapel::class);
    }

    public function keanggotaanDitinjau(): HasMany
    {
        return $this->hasMany(KeanggotaanRuangMapel::class, 'ditinjau_oleh');
    }
}
