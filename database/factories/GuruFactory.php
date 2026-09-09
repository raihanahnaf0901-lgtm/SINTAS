<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guru>
 */
class GuruFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'guru']),
            'nama_lengkap' => fake()->name(),
            'jenis_guru' => 'guru_mapel',
            'gelar' => fake()->randomElement(['S.Pd', 'M.Pd', 'S.Si']),
            'nip' => fake()->unique()->numerify('##################'),
        ];
    }
}
