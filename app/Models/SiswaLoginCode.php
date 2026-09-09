<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class SiswaLoginCode extends OtpVerification
{
    use HasFactory;

    protected $table = 'otp_verifications';

    protected $attributes = ['purpose' => 'login', 'attempts' => 0];
}
