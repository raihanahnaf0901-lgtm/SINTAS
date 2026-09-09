<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jadwal extends Model
{
    use HasFactory;

    protected $table = 'jadwal';

    protected $fillable = ['kelas_mapel_id', 'guru_id', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan'];

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }
}
