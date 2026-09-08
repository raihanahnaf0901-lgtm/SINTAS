<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Mapel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jadwal>
 */
class JadwalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kelas_id' => Kelas::factory(),
            'mapel_id' => Mapel::factory(),
            'guru_id' => Guru::factory(),
            'hari' => fake()->randomElement(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']),
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:30:00',
            'ruangan' => fake()->bothify('R-##'),
            'tahun_ajaran' => '2026/2027',
            'semester' => 'ganjil',
        ];
    }
}
