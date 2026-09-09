<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhitelistGuruKelas extends Model
{
    protected $table = 'whitelist_guru_kelas';

    protected $fillable = ['kelas_mapel_id', 'guru_id', 'ditambahkan_oleh', 'status'];

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function penambah(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'ditambahkan_oleh');
    }
}
