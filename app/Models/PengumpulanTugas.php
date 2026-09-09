<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengumpulanTugas extends Model
{
    protected $table = 'pengumpulan_tugas';

    protected $fillable = ['tugas_id', 'siswa_id', 'status', 'file_path', 'catatan_siswa', 'submitted_at'];

    protected $hidden = ['file_path'];

    protected $appends = ['has_file'];

    protected function hasFile(): Attribute
    {
        return Attribute::get(fn (): bool => filled($this->file_path));
    }

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(Tugas::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
