<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeanggotaanSekolah extends Model
{
    use HasFactory;

    protected $table = 'keanggotaan_sekolah';

    protected $fillable = ['sekolah_id', 'guru_id', 'jenis_guru', 'status', 'requested_at', 'reviewed_at', 'reviewed_by'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }
}
