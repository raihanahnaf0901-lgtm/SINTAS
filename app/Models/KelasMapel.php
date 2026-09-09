<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KelasMapel extends Model
{
    use HasFactory;

    protected $table = 'kelas_mapel';

    protected $fillable = ['mapel_id', 'guru_pembuat_id', 'nama_kelas_mapel', 'kode_kelas', 'invite_token', 'deskripsi', 'status'];

    protected $hidden = ['kode_kelas', 'invite_token'];

    protected $attributes = ['status' => 'aktif'];

    protected static function booted(): void
    {
        static::creating(function (KelasMapel $kelas): void {
            $kelas->kode_kelas = Str::upper($kelas->kode_kelas ?: Str::random(10));
            $kelas->invite_token ??= Str::random(48);
        });
        static::created(function (KelasMapel $kelas): void {
            $kelas->whitelist()->create([
                'guru_id' => $kelas->guru_pembuat_id,
                'ditambahkan_oleh' => $kelas->guru_pembuat_id,
                'status' => 'aktif',
            ]);
        });
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->status !== 'aktif') {
            return $query->whereRaw('1 = 0');
        }
        if ($user->role === 'guru' && $user->guru) {
            return $query->whereHas('whitelist', fn (Builder $q) => $q
                ->where('guru_id', $user->guru->id)->where('status', 'aktif'));
        }
        if ($user->role === 'siswa' && $user->siswa) {
            return $query->where('status', 'aktif')->whereHas('anggota', fn (Builder $q) => $q
                ->where('siswa_id', $user->siswa->id)->where('status', 'diterima'));
        }

        return $query->whereRaw('1 = 0');
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'guru_pembuat_id');
    }

    public function whitelist(): HasMany
    {
        return $this->hasMany(WhitelistGuruKelas::class);
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(AnggotaKelas::class);
    }

    public function tugas(): HasMany
    {
        return $this->hasMany(Tugas::class);
    }

    public function ujian(): HasMany
    {
        return $this->hasMany(Ujian::class);
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function konfigurasiRekap(): HasMany
    {
        return $this->hasMany(KonfigurasiRekap::class);
    }
}
