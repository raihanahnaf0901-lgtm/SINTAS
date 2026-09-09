<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\KelasMapel;
use App\Models\Mapel;
use Illuminate\Database\Eloquent\Factories\Factory;

class KelasMapelFactory extends Factory
{
    protected $model = KelasMapel::class;

    public function definition(): array
    {
        return ['mapel_id' => Mapel::factory(), 'guru_pembuat_id' => Guru::factory(),
            'nama_kelas_mapel' => fake()->words(3, true), 'status' => 'aktif'];
    }
}
