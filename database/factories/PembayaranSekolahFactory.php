<?php

namespace Database\Factories;

use App\Models\PembayaranSekolah;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PembayaranSekolah>
 */
class PembayaranSekolahFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory()->pending(),
            'order_id' => 'SINTAS-'.Str::ulid(),
            'plan_code' => 'test-plan',
            'plan_name' => 'Paket pengujian',
            'amount' => 100000,
            'duration_days' => 30,
            'status' => 'pending',
            'checkout_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/'.Str::uuid(),
        ];
    }
}
