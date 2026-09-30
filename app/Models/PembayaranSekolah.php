<?php

namespace App\Models;

use Database\Factories\PembayaranSekolahFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembayaranSekolah extends Model
{
    /** @use HasFactory<PembayaranSekolahFactory> */
    use HasFactory;

    protected $table = 'pembayaran_sekolah';

    protected $fillable = [
        'sekolah_id', 'order_id', 'plan_code', 'plan_name', 'amount', 'duration_days', 'duration_months',
        'status', 'checkout_url', 'transaction_id', 'gateway_status', 'paid_at', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'duration_days' => 'integer', 'duration_months' => 'integer',
            'paid_at' => 'datetime', 'activated_at' => 'datetime',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
