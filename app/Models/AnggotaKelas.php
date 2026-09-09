<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggotaKelas extends Model
{
    protected $table = 'anggota_kelas';

    protected $fillable = ['kelas_mapel_id', 'siswa_id', 'status', 'join_method', 'requested_at', 'approved_at', 'approved_by'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'approved_by');
    }
}
