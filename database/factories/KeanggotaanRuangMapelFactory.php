<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\KeanggotaanRuangMapel;
use App\Models\RuangMapel;
use App\Models\Siswa;
use App\StatusKeanggotaanRuangMapel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeanggotaanRuangMapel>
 */
class KeanggotaanRuangMapelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruang_mapel_id' => RuangMapel::factory(),
            'siswa_id' => Siswa::factory(),
            'status' => StatusKeanggotaanRuangMapel::Menunggu,
            'ditinjau_oleh' => null,
            'ditinjau_pada' => null,
        ];
    }

    public function diterima(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusKeanggotaanRuangMapel::Diterima,
            'ditinjau_oleh' => Guru::factory(),
            'ditinjau_pada' => now(),
        ]);
    }

    public function ditolak(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusKeanggotaanRuangMapel::Ditolak,
            'ditinjau_oleh' => Guru::factory(),
            'ditinjau_pada' => now(),
        ]);
    }
}
