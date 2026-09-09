<?php

namespace Database\Factories;

use App\Models\KelasMapel;
use Illuminate\Database\Eloquent\Factories\Factory;

class JadwalFactory extends Factory
{
    public function definition(): array
    {
        return ['kelas_mapel_id' => KelasMapel::factory(),
            'guru_id' => fn ($attributes) => KelasMapel::findOrFail($attributes['kelas_mapel_id'])->guru_pembuat_id,
            'hari' => 'senin', 'jam_mulai' => '07:00', 'jam_selesai' => '08:30', 'ruangan' => 'R-01'];
    }
}
