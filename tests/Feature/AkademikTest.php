<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\KelasMapel;
use App\Models\Notifikasi;
use App\Models\PengumpulanTugas;
use App\Models\Penilaian;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\Ujian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AkademikTest extends TestCase
{
    use RefreshDatabase;

    private function member(KelasMapel $kelas): Siswa
    {
        $siswa = Siswa::factory()->create();
        $kelas->anggota()->create(['siswa_id' => $siswa->id, 'status' => 'diterima',
            'join_method' => 'kode', 'requested_at' => now(), 'approved_at' => now(), 'approved_by' => $kelas->guru_pembuat_id]);

        return $siswa;
    }

    public function test_whitelisted_mapel_teacher_can_create_and_edit_academics(): void
    {
        $kelas = KelasMapel::factory()->create();
        $guru = Guru::factory()->create();
        $siswa = $this->member($kelas);
        $kelas->whitelist()->create(['guru_id' => $guru->id, 'ditambahkan_oleh' => $kelas->guru_pembuat_id, 'status' => 'aktif']);
        $base = '/api/v1/kelas-mapel/'.$kelas->id;
        $this->actingAs($guru->user);
        $data = ['judul' => 'Latihan', 'jenis' => 'pr', 'deadline' => now()->addDay()->toDateTimeString(), 'guru_id' => $kelas->guru_pembuat_id];
        $id = $this->postJson($base.'/tugas', $data)->assertCreated()->assertJsonPath('data.guru_id', $guru->id)->json('data.id');
        $this->patchJson($base.'/tugas/'.$id, [...$data, 'judul' => 'Latihan baru'])->assertOk()->assertJsonPath('data.judul', 'Latihan baru');
        $this->postJson($base.'/ujian', ['jenis_ujian' => 'UH', 'judul' => 'Ulangan', 'tanggal' => '2026-10-01'])->assertCreated();
        $this->postJson($base.'/jadwal', ['hari' => 'senin', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00'])->assertCreated();
        $this->postJson($base.'/jadwal', ['hari' => 'senin', 'jam_mulai' => '09:00', 'jam_selesai' => '08:00'])->assertUnprocessable();
        $this->assertDatabaseHas('notifikasi', ['user_id' => $siswa->user_id, 'tipe' => 'tugas']);
    }

    public function test_cross_class_task_edit_is_rejected(): void
    {
        $kelas = KelasMapel::factory()->create();
        $task = Tugas::factory()->create();
        $this->actingAs($kelas->pembuat->user)->patchJson('/api/v1/kelas-mapel/'.$kelas->id.'/tugas/'.$task->id, [
            'judul' => 'Changed', 'jenis' => 'tugas', 'deadline' => now()->toDateTimeString(),
        ])->assertNotFound();
    }

    public function test_submission_uses_authenticated_student_and_download_is_private(): void
    {
        Storage::fake('local');
        $kelas = KelasMapel::factory()->create();
        $student = $this->member($kelas);
        $other = $this->member($kelas);
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id, 'deadline' => now()->subMinute()]);
        $id = $this->actingAs($student->user)->postJson('/api/v1/kelas-mapel/'.$kelas->id.'/tugas/'.$task->id.'/pengumpulan', [
            'file' => UploadedFile::fake()->create('jawaban.pdf', 10, 'application/pdf'),
            'siswa_id' => $other->id, 'status' => 'dikumpulkan',
        ])->assertCreated()->assertJsonPath('data.siswa_id', $student->id)
            ->assertJsonPath('data.status', 'terlambat')->assertJsonMissingPath('data.file_path')->json('data.id');
        $submission = PengumpulanTugas::findOrFail($id);
        Storage::disk('local')->assertExists($submission->file_path);
        $this->get('/api/v1/pengumpulan/'.$id.'/file')->assertOk();
        $this->actingAs($other->user)->getJson('/api/v1/pengumpulan/'.$id.'/file')->assertForbidden();
        $this->actingAs($kelas->pembuat->user)->get('/api/v1/pengumpulan/'.$id.'/file')->assertOk();
        $this->actingAs(Guru::factory()->create()->user)->getJson('/api/v1/pengumpulan/'.$id.'/file')->assertForbidden();
    }

    public function test_grading_checks_target_membership_range_and_updates_one_record(): void
    {
        $kelas = KelasMapel::factory()->create();
        $siswa = $this->member($kelas);
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $exam = Ujian::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $url = '/api/v1/kelas-mapel/'.$kelas->id.'/penilaian';
        $this->actingAs($kelas->pembuat->user);
        $data = ['siswa_id' => $siswa->id, 'tugas_id' => $task->id, 'nilai' => 80];
        $this->putJson($url, [...$data, 'ujian_id' => $exam->id])->assertUnprocessable();
        $this->putJson($url, [...$data, 'nilai' => 101])->assertUnprocessable();
        $this->putJson($url, [...$data, 'siswa_id' => Siswa::factory()->create()->id])->assertUnprocessable();
        $this->putJson($url, [...$data, 'tugas_id' => Tugas::factory()->create()->id])->assertUnprocessable();
        $this->putJson($url, $data)->assertOk()->assertJsonPath('data.dinilai_oleh', $kelas->guru_pembuat_id);
        $this->putJson($url, [...$data, 'nilai' => 0])->assertOk()->assertJsonPath('data.nilai', '0.00');
        $this->assertDatabaseCount('penilaian', 1);
        $this->actingAs($siswa->user)->getJson('/api/v1/dashboard')->assertJsonPath('data.summaries.0.completed', 1);
        $this->postJson('/api/v1/kelas-mapel/'.$kelas->id.'/tugas/'.$task->id.'/pengumpulan', ['catatan_siswa' => 'Diubah'])->assertUnprocessable();
    }

    public function test_rekap_combines_average_with_persisted_manual_scores_and_checks_weights(): void
    {
        $kelas = KelasMapel::factory()->create();
        $siswa = $this->member($kelas);
        $other = $this->member($kelas);
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        Penilaian::create(['siswa_id' => $siswa->id, 'tugas_id' => $task->id, 'nilai' => 80,
            'dinilai_oleh' => $kelas->guru_pembuat_id, 'dinilai_at' => now()]);
        $base = '/api/v1/kelas-mapel/'.$kelas->id.'/rekap';
        $config = ['nama_konfigurasi' => 'Semester 1', 'status' => 'aktif', 'komponen' => [
            ['jenis' => 'tugas', 'bobot' => 40, 'metode' => 'rata_rata'],
            ['jenis' => 'US', 'bobot' => 60, 'metode' => 'manual'],
        ]];
        $this->actingAs($kelas->pembuat->user);
        $wrong = $config;
        $wrong['komponen'][1]['bobot'] = 50;
        $this->postJson($base, $wrong)->assertUnprocessable();
        $response = $this->postJson($base, $config)->assertCreated();
        $id = $response->json('data.id');
        $part = collect($response->json('data.komponen'))->firstWhere('jenis', 'US')['id'];
        $this->getJson($base.'/'.$id)->assertOk()->assertJsonPath('data.data.0.nilai_akhir', null);
        $this->putJson($base.'/'.$id.'/komponen/'.$part.'/manual', ['siswa_id' => $siswa->id, 'nilai' => 90])->assertOk();
        $this->assertDatabaseHas('nilai_rekap_manual', ['siswa_id' => $siswa->id, 'nilai' => 90, 'dinilai_oleh' => $kelas->guru_pembuat_id]);
        $this->actingAs($siswa->user)->getJson($base.'/'.$id)->assertOk()
            ->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.nilai_akhir', 86)
            ->assertJsonPath('data.data.0.lengkap', true);
        $this->actingAs($other->user)->getJson($base.'/'.$id)->assertJsonPath('data.data.0.nilai_akhir', null);
        $this->actingAs($siswa->user)->putJson($base.'/'.$id.'/komponen/'.$part.'/manual', ['siswa_id' => $siswa->id, 'nilai' => 100])->assertForbidden();
    }

    public function test_notifications_are_scoped_to_current_user(): void
    {
        $one = Siswa::factory()->create()->user;
        $two = Siswa::factory()->create()->user;
        $note = Notifikasi::create(['user_id' => $one->id, 'judul' => 'Nilai', 'pesan' => 'Ada nilai baru', 'tipe' => 'penilaian']);
        $this->actingAs($two)->getJson('/api/v1/notifikasi')->assertJsonCount(0, 'data.data');
        $this->patchJson('/api/v1/notifikasi/'.$note->id.'/baca')->assertNotFound();
        $this->actingAs($one)->patchJson('/api/v1/notifikasi/'.$note->id.'/baca')->assertOk();
        $this->assertNotNull($note->fresh()->read_at);
    }
}
