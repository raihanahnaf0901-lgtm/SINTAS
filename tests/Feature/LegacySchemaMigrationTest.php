<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LegacySchemaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_ids_membership_codes_grades_and_notification_recipient_are_preserved(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        DB::table('gurus')->insert(['id' => 31, 'user_id' => $teacher->id, 'nama' => 'Guru Lama', 'nip' => '0101', 'gelar' => 'S.Pd']);
        DB::table('kelas')->insert(['id' => 21, 'nama_kelas' => 'Kelas Lama', 'tingkat' => '10']);
        DB::table('siswas')->insert(['id' => 41, 'user_id' => $student->id, 'kelas_id' => 21, 'nis' => '00123', 'nama_lengkap' => 'Siswa Lama']);
        DB::table('mapels')->insert(['id' => 51, 'kode_mapel' => 'MTK', 'nama_mapel' => 'Matematika']);
        DB::table('ruang_mapels')->insert(['id' => 61, 'mapel_id' => 51, 'guru_id' => 31, 'kelas_id' => 21,
            'nama_ruang' => 'Ruang Lama', 'kode' => 'LAMA-001', 'aktif' => true]);
        DB::table('keanggotaan_ruang_mapels')->insert(['id' => 71, 'ruang_mapel_id' => 61, 'siswa_id' => 41,
            'status' => 'diterima', 'ditinjau_oleh' => 31, 'ditinjau_pada' => now()]);
        DB::table('jadwals')->insert(['id' => 81, 'guru_id' => 31, 'mapel_id' => 51, 'kelas_id' => 21,
            'hari' => 'Senin', 'jam_mulai' => '07:00', 'jam_selesai' => '08:30']);
        DB::table('legacy_tugas')->insert(['id' => 91, 'guru_id' => 31, 'mapel_id' => 51, 'kelas_id' => 21,
            'judul' => 'Tugas Lama', 'deadline' => now()->addDay()]);
        DB::table('penilaians')->insert(['id' => 101, 'siswa_id' => 41, 'guru_id' => 31, 'mapel_id' => 51,
            'kelas_id' => 21, 'tugas_id' => 91, 'jenis_penilaian' => 'Tugas', 'nilai' => 87]);
        DB::table('legacy_notifikasi')->insert(['id' => 111, 'tipe_user' => 'Siswa', 'user_id' => 41,
            'judul' => 'Nilai', 'pesan' => 'Nilai tersedia', 'waktu_kirim' => now(), 'is_read' => true]);
        DB::table('siswa_login_codes')->insert(['id' => 121, 'user_id' => $student->id, 'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10)]);

        $migration = require database_path('migrations/2026_09_09_120100_import_legacy_sintas_data.php');
        $migration->up();

        $this->assertDatabaseHas('siswa', ['id' => 41, 'user_id' => $student->id, 'nis' => '00123']);
        $this->assertDatabaseHas('guru', ['id' => 31, 'nama_lengkap' => 'Guru Lama']);
        $this->assertDatabaseHas('kelas_mapel', ['id' => 61, 'kode_kelas' => 'LAMA-001', 'guru_pembuat_id' => 31]);
        $this->assertDatabaseHas('anggota_kelas', ['id' => 71, 'siswa_id' => 41, 'status' => 'diterima', 'approved_by' => 31]);
        $this->assertDatabaseHas('whitelist_guru_kelas', ['kelas_mapel_id' => 61, 'guru_id' => 31, 'status' => 'aktif']);
        $this->assertDatabaseHas('jadwal', ['id' => 81, 'kelas_mapel_id' => 61, 'hari' => 'senin']);
        $this->assertDatabaseHas('tugas', ['id' => 91, 'kelas_mapel_id' => 61]);
        $this->assertDatabaseHas('penilaian', ['id' => 101, 'tugas_id' => 91, 'nilai' => 87, 'dinilai_oleh' => 31]);
        $this->assertDatabaseHas('notifikasi', ['id' => 111, 'user_id' => $student->id]);
        $this->assertDatabaseHas('otp_verifications', ['id' => 121, 'purpose' => 'login']);
        $this->assertDatabaseCount('legacy_tugas', 1);
        $this->assertDatabaseCount('penilaians', 1);
    }

    public function test_invalid_legacy_data_aborts_import_without_partial_copy(): void
    {
        $user = User::factory()->create(['role' => 'guru']);
        DB::table('gurus')->insert(['id' => 31, 'user_id' => $user->id, 'nama' => 'Guru', 'gelar' => 'S.Pd']);
        DB::table('mapels')->insert(['id' => 51, 'kode_mapel' => 'MTK', 'nama_mapel' => 'Matematika']);
        DB::table('siswas')->insert(['id' => 41, 'nama_lengkap' => 'Siswa']);
        DB::table('penilaians')->insert(['siswa_id' => 41, 'guru_id' => 31, 'mapel_id' => 51,
            'jenis_penilaian' => 'Ujian Lama', 'nilai' => 80]);
        $migration = require database_path('migrations/2026_09_09_120100_import_legacy_sintas_data.php');
        try {
            $migration->up();
            $this->fail('Expected migration to reject an unmapped grade.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('tanpa tugas', $exception->getMessage());
        }
        $this->assertDatabaseCount('guru', 0);
        $this->assertDatabaseCount('siswa', 0);
        $this->assertDatabaseCount('penilaians', 1);
    }
}
