<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
        {
            // 1. Buat Data Kelas
            $kelas = \App\Models\Kelas::create([
                'nama_kelas' => '10 Akuntansi 1'
            ]);

            // 2. Buat Data Siswa
            \App\Models\Siswa::create([
                'nama_lengkap' => 'Raihan Ahnaf',
                'nis'          => '12345',
                'kelas_id'     => $kelas->id,
            ]);

            // 3. Buat Data Guru
            \App\Models\Guru::create([
                'nama'  => 'Budi Santoso',
                'gelar' => 'S.Pd',
                'nip'   => '19850101',
            ]);
        }
}
