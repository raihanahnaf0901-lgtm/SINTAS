<?php

namespace Database\Factories;

use App\Models\Kelas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kelas>
 */
class KelasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_kelas' => fake()->unique()->bothify('## ?? #'),
            'tingkat' => fake()->randomElement(['10', '11', '12']),
            'jurusan' => fake()->randomElement(['IPA', 'IPS', 'Akuntansi']),
            'tahun_ajaran' => '2026/2027',
            'wali_kelas_id' => null,
        ];
    }
}
