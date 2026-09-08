<?php

namespace App\Models;

use Database\Factories\SiswaLoginCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiswaLoginCode extends Model
{
    /** @use HasFactory<SiswaLoginCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code_hash',
        'attempts',
        'expires_at',
        'used_at',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected $attributes = [
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
