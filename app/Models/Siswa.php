<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siswa extends Model
{
    use HasFactory;

    protected $table = 'siswa';

    protected $fillable = ['user_id', 'kelas_id', 'nis', 'nisn', 'nama_lengkap'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function anggotaKelas(): HasMany
    {
        return $this->hasMany(AnggotaKelas::class);
    }

    public function kelasMapel(): BelongsToMany
    {
        return $this->belongsToMany(KelasMapel::class, 'anggota_kelas')
            ->wherePivot('status', 'diterima')->withPivot(['status', 'join_method', 'approved_at'])->withTimestamps();
    }

    public function penilaian(): HasMany
    {
        return $this->hasMany(Penilaian::class);
    }

    public function pengumpulan(): HasMany
    {
        return $this->hasMany(PengumpulanTugas::class);
    }
}
