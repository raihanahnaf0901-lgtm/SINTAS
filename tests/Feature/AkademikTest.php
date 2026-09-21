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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
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

    #[TestWith([true, ''])]
    #[TestWith([true, 'Sudah mengerjakan tugas'])]
    #[TestWith([false, 'Jawaban tertulis tanpa berkas'])]
    public function test_submission_accepts_a_file_or_answer_with_optional_notes(bool $withFile, string $note): void
    {
        Storage::fake('local');
        $kelas = KelasMapel::factory()->create();
        $siswa = $this->member($kelas);
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id, 'deadline' => now()->addDay()]);
        $data = ['catatan_siswa' => $note];
        if ($withFile) {
            $data['file'] = UploadedFile::fake()->create('jawaban.png', 10240, 'image/png');
        }

        $this->actingAs($siswa->user)->post('/api/v1/kelas-mapel/'.$kelas->id.'/tugas/'.$task->id.'/pengumpulan', $data, ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.status', 'dikumpulkan')->assertJsonPath('data.has_file', $withFile);

        $submission = PengumpulanTugas::sole();
        $this->assertSame($note === '' ? null : $note, $submission->catatan_siswa);
        if ($withFile) {
            Storage::disk('local')->assertExists($submission->file_path);
        } else {
            $this->assertNull($submission->file_path);
        }
    }

    #[TestWith(['empty', 'Pilih berkas tugas atau tulis jawaban sebelum mengumpulkan.'])]
    #[TestWith(['size', 'Ukuran berkas tugas maksimal 10 MB.'])]
    #[TestWith(['type', 'Format berkas harus PDF, Word, JPG, PNG, atau ZIP.'])]
    #[TestWith(['upload', 'Berkas gagal diterima server. Periksa ukuran berkas dan batas upload PHP, lalu pilih ulang berkas.'])]
    #[TestWith(['temporary_directory', 'Folder sementara upload server tidak tersedia. Hubungi pengelola SINTAS untuk memperbaiki konfigurasi PHP.'])]
    #[TestWith(['write', 'Server tidak dapat menulis berkas. Hubungi pengelola SINTAS untuk memeriksa penyimpanan server.'])]
    #[TestWith(['partial', 'Berkas hanya terkirim sebagian. Pilih ulang berkas dan coba kirim lagi.'])]
    public function test_rejected_submission_returns_a_specific_file_error(string $reason, string $message): void
    {
        Storage::fake('local');
        $kelas = KelasMapel::factory()->create();
        $siswa = $this->member($kelas);
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $file = match ($reason) {
            'size' => UploadedFile::fake()->create('jawaban.pdf', 10241, 'application/pdf'),
            'type' => UploadedFile::fake()->create('jawaban.exe', 10, 'application/x-msdownload'),
            'upload' => new UploadedFile('', 'jawaban.png', 'image/png', UPLOAD_ERR_INI_SIZE, true),
            'temporary_directory' => new UploadedFile('', 'jawaban.png', 'image/png', UPLOAD_ERR_NO_TMP_DIR, true),
            'write' => new UploadedFile('', 'jawaban.png', 'image/png', UPLOAD_ERR_CANT_WRITE, true),
            'partial' => new UploadedFile('', 'jawaban.png', 'image/png', UPLOAD_ERR_PARTIAL, true),
            default => null,
        };

        $this->actingAs($siswa->user)->post('/api/v1/kelas-mapel/'.$kelas->id.'/tugas/'.$task->id.'/pengumpulan', ['file' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.file.0', $message);

        $this->assertDatabaseCount('pengumpulan_tugas', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
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

    public function test_upload_rejection_is_logged_without_the_students_answer_or_filename(): void
    {
        Log::spy();
        $kelas = KelasMapel::factory()->create();
        $siswa = $this->member($kelas);
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $path = 'api/v1/kelas-mapel/'.$kelas->id.'/tugas/'.$task->id.'/pengumpulan';

        $this->actingAs($siswa->user)->post('/'.$path, [
            'file' => new UploadedFile('', 'jawaban-pribadi.png', 'image/png', UPLOAD_ERR_INI_SIZE, true),
            'catatan_siswa' => 'Jawaban siswa yang tidak boleh masuk log.',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

        Log::shouldHaveReceived('notice')->once()->with('Pengumpulan tugas ditolak oleh validasi.', [
            'path' => $path, 'fields' => ['file'], 'rules' => ['file' => ['uploaded' => []]],
            'upload_error' => UPLOAD_ERR_INI_SIZE, 'file_size' => null,
        ]);
        $this->assertDatabaseCount('pengumpulan_tugas', 0);
    }

    public function test_real_png_contents_pass_mime_validation_with_optional_empty_notes(): void
    {
        Storage::fake('local');
        $kelas = KelasMapel::factory()->create();
        $student = $this->member($kelas);
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9X8AAAAASUVORK5CYII=');
        $temporaryFile = UploadedFile::fake()->createWithContent('jawaban.png', $contents);
        $file = new UploadedFile($temporaryFile->getPathname(), 'jawaban.png', 'image/png', UPLOAD_ERR_OK, true);

        $this->actingAs($student->user)->post('/api/v1/kelas-mapel/'.$kelas->id.'/tugas/'.$task->id.'/pengumpulan', [
            'file' => $file, 'catatan_siswa' => '',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.has_file', true);

        $submission = PengumpulanTugas::sole();
        $this->assertSame($contents, Storage::disk('local')->get($submission->file_path));
        $this->assertNull($submission->catatan_siswa);
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

    public function test_new_task_notifies_only_accepted_students_and_can_be_marked_read(): void
    {
        $this->freezeTime();
        $kelas = KelasMapel::factory()->create();
        $student = $this->member($kelas);
        $pending = $this->member($kelas);
        $kelas->anggota()->where('siswa_id', $pending->id)->update(['status' => 'pending']);
        $other = Siswa::factory()->create();

        $taskId = $this->actingAs($kelas->pembuat->user)->postJson('/api/v1/kelas-mapel/'.$kelas->id.'/tugas', [
            'judul' => 'Tugas terbaru', 'jenis' => 'tugas', 'deadline' => now()->addDay()->toDateTimeString(),
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseCount('notifikasi', 1);
        $notificationId = $this->actingAs($student->user)->getJson('/api/v1/notifikasi')
            ->assertJsonPath('belum_dibaca', 1)->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.pesan', 'Tugas terbaru')
            ->assertJsonPath('data.data.0.data.kelas_mapel_id', $kelas->id)
            ->assertJsonPath('data.data.0.data.tugas_id', $taskId)->json('data.data.0.id');
        $this->patchJson('/api/v1/notifikasi/'.$notificationId.'/baca')->assertOk();
        $this->getJson('/api/v1/notifikasi')->assertJsonPath('belum_dibaca', 0);
        $this->assertNotNull(Notifikasi::findOrFail($notificationId)->read_at);
        $this->actingAs($pending->user)->getJson('/api/v1/notifikasi')->assertJsonCount(0, 'data.data');
        $this->actingAs($other->user)->getJson('/api/v1/notifikasi')->assertJsonCount(0, 'data.data');
    }
}
