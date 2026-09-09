<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guru extends Model
{
    use HasFactory;

    protected $table = 'guru';

    protected $fillable = ['user_id', 'nama_lengkap', 'gelar', 'nip', 'jenis_guru'];

    protected $attributes = ['jenis_guru' => 'guru_mapel'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function kelasMapelDibuat(): HasMany
    {
        return $this->hasMany(KelasMapel::class, 'guru_pembuat_id');
    }

    public function whitelist(): HasMany
    {
        return $this->hasMany(WhitelistGuruKelas::class);
    }
}
