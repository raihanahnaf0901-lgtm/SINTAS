<?php

namespace App\Models;

use App\StatusKeanggotaanRuangMapel;
use Database\Factories\KeanggotaanRuangMapelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeanggotaanRuangMapel extends Model
{
    /** @use HasFactory<KeanggotaanRuangMapelFactory> */
    use HasFactory;

    protected $fillable = [
        'ruang_mapel_id',
        'siswa_id',
        'status',
        'ditinjau_oleh',
        'ditinjau_pada',
    ];

    protected $attributes = [
        'status' => StatusKeanggotaanRuangMapel::Menunggu->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusKeanggotaanRuangMapel::class,
            'ditinjau_pada' => 'datetime',
        ];
    }

    public function ruangMapel(): BelongsTo
    {
        return $this->belongsTo(RuangMapel::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function peninjau(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'ditinjau_oleh');
    }
}
