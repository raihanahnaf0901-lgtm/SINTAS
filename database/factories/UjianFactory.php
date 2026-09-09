<?php

namespace Database\Factories;

use App\Models\KelasMapel;
use Illuminate\Database\Eloquent\Factories\Factory;

class UjianFactory extends Factory
{
    public function definition(): array
    {
        return ['kelas_mapel_id' => KelasMapel::factory(),
            'guru_id' => fn ($attributes) => KelasMapel::findOrFail($attributes['kelas_mapel_id'])->guru_pembuat_id,
            'judul' => fake()->sentence(), 'jenis_ujian' => 'UH', 'tanggal' => now()->addDays(3)->toDateString()];
    }
}
