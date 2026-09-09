<?php

namespace Tests\Feature;

use App\Models\KelasMapel;
use App\Models\Siswa;
use App\Models\Tugas;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinalSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_final_tables_and_manual_grade_extension_exist(): void
    {
        foreach (['users', 'otp_verifications', 'siswa', 'guru', 'kelas', 'mapel', 'kelas_mapel', 'whitelist_guru_kelas',
            'anggota_kelas', 'jadwal', 'tugas', 'ujian', 'pengumpulan_tugas', 'penilaian', 'konfigurasi_rekap',
            'komponen_rekap', 'notifikasi', 'nilai_rekap_manual'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        $this->assertTrue(Schema::hasColumns('users', ['username', 'status']));
        $this->assertTrue(Schema::hasColumns('guru', ['nama_lengkap', 'jenis_guru']));
    }

    public function test_database_rejects_grade_without_exactly_one_target(): void
    {
        $kelas = KelasMapel::factory()->create();
        $siswa = Siswa::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('penilaian')->insert(['siswa_id' => $siswa->id, 'nilai' => 80,
            'dinilai_oleh' => $kelas->guru_pembuat_id, 'dinilai_at' => now()]);
    }

    public function test_database_rejects_duplicate_submission(): void
    {
        $task = Tugas::factory()->create();
        $siswa = Siswa::factory()->create();
        $data = ['tugas_id' => $task->id, 'siswa_id' => $siswa->id, 'status' => 'belum'];
        DB::table('pengumpulan_tugas')->insert($data);
        $this->expectException(QueryException::class);
        DB::table('pengumpulan_tugas')->insert($data);
    }

    public function test_new_owner_is_in_whitelist_and_secrets_are_hidden(): void
    {
        $kelas = KelasMapel::factory()->create();
        $this->assertDatabaseHas('whitelist_guru_kelas', ['kelas_mapel_id' => $kelas->id,
            'guru_id' => $kelas->guru_pembuat_id, 'status' => 'aktif']);
        $this->assertArrayNotHasKey('invite_token', $kelas->toArray());
        $this->assertArrayNotHasKey('kode_kelas', $kelas->toArray());
    }
}
