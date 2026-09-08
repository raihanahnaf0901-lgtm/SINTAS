<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\KeanggotaanRuangMapel;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\RuangMapel;
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

        $siswaUser = User::query()
            ->whereIn('email', ['raihan@belajar.id', 'raihan@sintas.test'])
            ->first() ?? new User;

        $siswaUser->fill([
            'name' => 'Raihan Ahnaf',
            'email' => 'raihan@belajar.id',
            'password' => Hash::make('password'),
            'role' => 'siswa',
        ])->save();

        $siswa = Siswa::query()->updateOrCreate([
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

        $guru = Guru::query()->firstOrCreate([
            'nip' => '19850101',
        ], [
            'user_id' => $guruUser->id,
            'nama' => 'Budi Santoso',
            'gelar' => 'S.Pd',
        ]);

        $mapel = Mapel::query()->firstOrCreate([
            'kode_mapel' => 'MTK',
        ], [
            'nama_mapel' => 'Matematika',
            'kelompok' => 'Wajib',
        ]);

        Jadwal::query()->firstOrCreate([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
        ], [
            'jam_selesai' => '08:30:00',
            'tahun_ajaran' => '2026/2027',
            'semester' => 'ganjil',
        ]);

        $ruangMapel = RuangMapel::query()->firstOrCreate([
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'kelas_id' => $kelas->id,
        ], [
            'nama_ruang' => 'Matematika - 10 Akuntansi 1',
            'kode' => 'RM-MTK10A1',
        ]);

        KeanggotaanRuangMapel::query()->firstOrCreate([
            'ruang_mapel_id' => $ruangMapel->id,
            'siswa_id' => $siswa->id,
        ]);
    }
}
