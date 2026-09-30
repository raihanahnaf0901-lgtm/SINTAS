<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\KeanggotaanSekolah;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/** @extends Factory<Sekolah> */
class SekolahFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_sekolah' => 'SMK '.fake()->unique()->city(),
            'npsn' => fake()->unique()->numerify('########'),
            'admin_guru_id' => Guru::factory(),
            'status' => 'aktif',
            'subscription_ends_at' => now()->addDays(30),
        ];
    }

    public function withAdmin(): static
    {
        return $this->afterCreating(function (Sekolah $sekolah): void {
            KeanggotaanSekolah::factory()->create([
                'sekolah_id' => $sekolah->id,
                'guru_id' => $sekolah->admin_guru_id,
                'status' => 'diterima',
                'reviewed_at' => now(),
            ]);
            $sekolah->admin->user->assignRole(Role::findOrCreate('admin_sekolah', 'web'));
        });
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending', 'subscription_ends_at' => null]);
    }
}
