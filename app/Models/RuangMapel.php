<?php

namespace App\Models;

use App\StatusKeanggotaanRuangMapel;
use Database\Factories\RuangMapelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RuangMapel extends Model
{
    /** @use HasFactory<RuangMapelFactory> */
    use HasFactory;

    protected $fillable = [
        'mapel_id',
        'guru_id',
        'kelas_id',
        'nama_ruang',
        'kode',
        'aktif',
    ];

    protected $attributes = [
        'aktif' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (RuangMapel $ruangMapel): void {
            if (filled($ruangMapel->kode)) {
                $ruangMapel->kode = Str::upper($ruangMapel->kode);

                return;
            }

            do {
                $ruangMapel->kode = 'RM-'.Str::upper(Str::random(8));
            } while (static::query()->where('kode', $ruangMapel->kode)->exists());
        });
    }

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'kode';
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function keanggotaans(): HasMany
    {
        return $this->hasMany(KeanggotaanRuangMapel::class);
    }

    public function permintaanMenunggu(): HasMany
    {
        return $this->keanggotaans()
            ->where('status', StatusKeanggotaanRuangMapel::Menunggu->value);
    }

    public function siswaDiterima(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'keanggotaan_ruang_mapels')
            ->wherePivot('status', StatusKeanggotaanRuangMapel::Diterima->value)
            ->withPivot(['status', 'ditinjau_oleh', 'ditinjau_pada'])
            ->withTimestamps();
    }
}
