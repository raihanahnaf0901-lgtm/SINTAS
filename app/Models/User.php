<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'status', 'email_verified_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['role' => 'siswa', 'status' => 'pending'];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->username ??= 'user_'.Str::lower((string) Str::ulid());
        });
    }

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function siswa(): HasOne
    {
        return $this->hasOne(Siswa::class);
    }

    public function isSchoolAdmin(?Sekolah $sekolah = null): bool
    {
        if ($this->role !== 'guru' || $this->status !== 'aktif' || ! $this->guru || ! $this->hasRole('admin_sekolah')) {
            return false;
        }

        return $this->guru->keanggotaanSekolah()->where('status', 'diterima')
            ->when($sekolah !== null, fn ($query) => $query->where('sekolah_id', $sekolah->id))
            ->whereHas('sekolah', fn ($query) => $query->where('status', 'aktif')
                ->where('admin_guru_id', $this->guru->id))->exists();
    }

    public function guru(): HasOne
    {
        return $this->hasOne(Guru::class);
    }

    public function otpVerifications(): HasMany
    {
        return $this->hasMany(OtpVerification::class);
    }

    public function siswaLoginCodes(): HasMany
    {
        return $this->hasMany(SiswaLoginCode::class)->where('purpose', 'login');
    }

    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class);
    }
}
