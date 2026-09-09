<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ujian extends Model
{
    use HasFactory;

    protected $table = 'ujian';

    protected $fillable = ['kelas_mapel_id', 'guru_id', 'jenis_ujian', 'judul', 'tanggal', 'keterangan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function penilaian(): HasMany
    {
        return $this->hasMany(Penilaian::class);
    }
}
