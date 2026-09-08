<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\RuangMapel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuangMapel>
 */
class RuangMapelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mapel_id' => Mapel::factory(),
            'guru_id' => Guru::factory(),
            'kelas_id' => Kelas::factory(),
            'nama_ruang' => fake()->words(3, true),
            'aktif' => true,
        ];
    }
}
