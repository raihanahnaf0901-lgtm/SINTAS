<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\KeanggotaanSekolah;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KeanggotaanSekolah> */
class KeanggotaanSekolahFactory extends Factory
{
    public function definition(): array
    {
        return ['sekolah_id' => Sekolah::factory(), 'guru_id' => Guru::factory(),
            'status' => 'pending', 'requested_at' => now()];
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => 'diterima', 'reviewed_at' => now()]);
    }
}
