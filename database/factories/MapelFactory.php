<?php

namespace Database\Factories;

use App\Models\Mapel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mapel>
 */
class MapelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_mapel' => fake()->unique()->bothify('MP-####'),
            'nama_mapel' => fake()->randomElement([
                'Matematika',
                'Bahasa Indonesia',
                'Fisika',
                'Biologi',
            ]),
            'deskripsi' => fake()->sentence(),
            'kelompok' => 'Wajib',
        ];
    }
}
