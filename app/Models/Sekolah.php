<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Sekolah extends Model
{
    use HasFactory;

    protected $table = 'sekolah';

    protected $fillable = ['nama_sekolah', 'npsn', 'alamat', 'admin_guru_id', 'kode_sekolah', 'status', 'subscription_ends_at'];

    protected $attributes = ['status' => 'pending'];

    protected static function booted(): void
    {
        static::creating(function (Sekolah $sekolah): void {
            $sekolah->kode_sekolah ??= Str::upper(Str::random(12));
        });
    }

    protected function casts(): array
    {
        return ['subscription_ends_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'admin_guru_id');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(KeanggotaanSekolah::class);
    }

    public function kelasMapel(): HasMany
    {
        return $this->hasMany(KelasMapel::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PembayaranSekolah::class);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->status === 'aktif' && $this->subscription_ends_at?->isFuture() === true;
    }
}
