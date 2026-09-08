<?php

namespace App\Models;

use Database\Factories\SiswaRegistrationCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiswaRegistrationCode extends Model
{
    /** @use HasFactory<SiswaRegistrationCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'password_hash',
        'code_hash',
        'attempts',
        'expires_at',
        'used_at',
    ];

    protected $hidden = [
        'password_hash',
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
}
