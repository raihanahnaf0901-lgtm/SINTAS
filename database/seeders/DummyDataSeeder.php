<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kelas = Kelas::query()->firstOrCreate([
            'nama_kelas' => '10 Akuntansi 1',
        ], [
            'tingkat' => '10',
        ]);

        $siswaUser = User::query()->firstOrCreate([
            'email' => 'raihan@sintas.test',
        ], [
            'name' => 'Raihan Ahnaf',
            'password' => Hash::make('password'),
            'role' => 'siswa',
        ]);

        Siswa::query()->firstOrCreate([
            'nis' => '12345',
        ], [
            'user_id' => $siswaUser->id,
            'kelas_id' => $kelas->id,
            'nama_lengkap' => 'Raihan Ahnaf',
        ]);

        $guruUser = User::query()->firstOrCreate([
            'email' => 'budi@sintas.test',
        ], [
            'name' => 'Budi Santoso',
            'password' => Hash::make('password'),
            'role' => 'guru',
        ]);

        Guru::query()->firstOrCreate([
            'nip' => '19850101',
        ], [
            'user_id' => $guruUser->id,
            'nama' => 'Budi Santoso',
            'gelar' => 'S.Pd',
        ]);
    }
}
