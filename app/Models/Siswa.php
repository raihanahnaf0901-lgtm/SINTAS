<?php

namespace App\Models;

use App\StatusKeanggotaanRuangMapel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siswa extends Model
{
    use HasFactory;

    protected $table = 'siswas';

    protected $fillable = [
        'user_id',
        'kelas_id',
        'nis',
        'nisn',
        'nama_lengkap',
        'status',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keanggotaanRuangMapels(): HasMany
    {
        return $this->hasMany(KeanggotaanRuangMapel::class);
    }

    public function ruangMapels(): BelongsToMany
    {
        return $this->belongsToMany(RuangMapel::class, 'keanggotaan_ruang_mapels')
            ->wherePivot('status', StatusKeanggotaanRuangMapel::Diterima->value)
            ->withPivot(['status', 'ditinjau_oleh', 'ditinjau_pada'])
            ->withTimestamps();
    }
}
