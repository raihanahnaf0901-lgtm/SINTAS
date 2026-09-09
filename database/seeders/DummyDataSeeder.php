<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\Mapel;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $class = Kelas::query()->firstOrCreate(['nama_kelas' => '10 Akuntansi 1'], [
            'tahun_ajaran' => '2026/2027', 'status' => 'aktif',
        ]);
        $student = User::query()->firstOrCreate(['email' => 'siswa@sintas.test'], [
            'name' => 'Siswa Contoh', 'username' => 'siswa_contoh', 'password' => 'password',
            'role' => 'siswa', 'status' => 'aktif', 'email_verified_at' => now(),
        ]);
        Siswa::query()->firstOrCreate(['user_id' => $student->id], [
            'nama_lengkap' => $student->name, 'nis' => 'DEMO-001', 'kelas_id' => $class->id,
        ]);
        $teachers = [];
        foreach (['guru_mapel' => 'Guru Mapel Contoh', 'guru_piket' => 'Guru Piket Contoh'] as $type => $name) {
            $user = User::query()->firstOrCreate(['email' => $type.'@sintas.test'], [
                'name' => $name, 'username' => $type.'_contoh', 'password' => 'password',
                'role' => 'guru', 'status' => 'aktif', 'email_verified_at' => now(),
            ]);
            $teachers[$type] = Guru::query()->firstOrCreate(['user_id' => $user->id], [
                'nama_lengkap' => $name, 'nip' => 'DEMO-'.$type, 'jenis_guru' => $type, 'gelar' => 'S.Pd',
            ]);
        }
        $mapel = Mapel::query()->firstOrCreate(['nama_mapel' => 'Matematika']);
        $kelas = KelasMapel::query()->firstOrCreate(['kode_kelas' => 'DEMO-MTK'], [
            'mapel_id' => $mapel->id, 'guru_pembuat_id' => $teachers['guru_mapel']->id,
            'nama_kelas_mapel' => 'Matematika - 10 Akuntansi 1',
        ]);
        $kelas->whitelist()->firstOrCreate(['guru_id' => $teachers['guru_piket']->id], [
            'ditambahkan_oleh' => $teachers['guru_mapel']->id, 'status' => 'aktif',
        ]);
    }
}
