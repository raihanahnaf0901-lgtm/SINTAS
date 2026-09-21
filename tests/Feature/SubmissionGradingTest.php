<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\KelasMapel;
use App\Models\Penilaian;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\Ujian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionGradingTest extends TestCase
{
    use RefreshDatabase;

    private function member(KelasMapel $kelas): Siswa
    {
        $student = Siswa::factory()->create();
        $kelas->anggota()->create(['siswa_id' => $student->id, 'status' => 'diterima', 'join_method' => 'kode', 'requested_at' => now()]);

        return $student;
    }

    public function test_submission_list_matches_grades_by_both_student_and_task_and_keeps_other_students_private(): void
    {
        $this->freezeTime();
        $kelas = KelasMapel::factory()->create();
        $first = $this->member($kelas);
        $second = $this->member($kelas);
        $ungraded = $this->member($kelas);
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $otherTask = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        foreach ([$first, $second, $ungraded] as $student) {
            $task->pengumpulan()->create(['siswa_id' => $student->id, 'status' => 'dikumpulkan', 'submitted_at' => now(), 'catatan_siswa' => 'Jawaban']);
        }
        foreach ([[$first, $task, 0], [$second, $task, 85], [$first, $otherTask, 40]] as [$student, $target, $score]) {
            Penilaian::create(['siswa_id' => $student->id, 'tugas_id' => $target->id, 'nilai' => $score,
                'dinilai_oleh' => $kelas->guru_pembuat_id, 'dinilai_at' => now()]);
        }
        $url = '/api/v1/kelas-mapel/'.$kelas->id.'/tugas/'.$task->id.'/pengumpulan';

        $response = $this->actingAs($kelas->pembuat->user)->getJson($url)->assertJsonCount(3, 'data.data');

        $records = collect($response->json('data.data'))->keyBy('siswa_id');
        $this->assertSame('0.00', $records[$first->id]['penilaian']['nilai']);
        $this->assertSame($kelas->pembuat->nama_lengkap, $records[$first->id]['penilaian']['penilai']['nama_lengkap']);
        $this->assertSame('85.00', $records[$second->id]['penilaian']['nilai']);
        $this->assertNull($records[$ungraded->id]['penilaian']);
        $this->actingAs($first->user)->getJson($url)->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.siswa_id', $first->id)->assertJsonPath('data.data.0.penilaian.nilai', '0.00')
            ->assertJsonMissingPath('data.data.0.file_path');
    }

    public function test_grading_and_editing_a_submission_recalculates_the_weighted_semester_recap(): void
    {
        $this->freezeTime();
        $kelas = KelasMapel::factory()->create();
        $student = $this->member($kelas);
        $first = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id, 'deadline' => now()->addDay()]);
        $second = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $exam = Ujian::factory()->create(['kelas_mapel_id' => $kelas->id, 'jenis_ujian' => 'US']);
        $base = '/api/v1/kelas-mapel/'.$kelas->id;
        $this->actingAs($student->user)->postJson($base.'/tugas/'.$first->id.'/pengumpulan', ['catatan_siswa' => 'Jawaban lengkap'])
            ->assertCreated();
        $this->actingAs($kelas->pembuat->user);
        $configId = $this->postJson($base.'/rekap', ['nama_konfigurasi' => 'Akhir semester', 'status' => 'aktif', 'komponen' => [
            ['jenis' => 'tugas', 'bobot' => 40, 'metode' => 'rata_rata'],
            ['jenis' => 'US', 'bobot' => 60, 'metode' => 'rata_rata'],
        ]])->assertCreated()->json('data.id');
        $gradeId = $this->putJson($base.'/penilaian', ['siswa_id' => $student->id, 'tugas_id' => $first->id, 'nilai' => 60])
            ->assertOk()->json('data.id');
        $this->putJson($base.'/penilaian', ['siswa_id' => $student->id, 'ujian_id' => $exam->id, 'nilai' => 90])->assertOk();
        $this->getJson($base.'/rekap/'.$configId)->assertJsonPath('data.data.0.nilai_akhir', null)->assertJsonPath('data.data.0.lengkap', false);
        $this->putJson($base.'/penilaian', ['siswa_id' => $student->id, 'tugas_id' => $second->id, 'nilai' => 80])->assertOk();
        $this->getJson($base.'/rekap/'.$configId)->assertJsonPath('data.data.0.nilai_akhir', 82)->assertJsonPath('data.data.0.lengkap', true);

        $this->putJson($base.'/penilaian', ['siswa_id' => $student->id, 'tugas_id' => $first->id, 'nilai' => 100, 'catatan' => 'Nilai setelah pemeriksaan ulang'])
            ->assertOk()->assertJsonPath('data.id', $gradeId)->assertJsonPath('data.nilai', '100.00');

        $this->assertDatabaseCount('penilaian', 3);
        $this->assertDatabaseHas('penilaian', ['id' => $gradeId, 'nilai' => 100, 'dinilai_oleh' => $kelas->guru_pembuat_id]);
        $this->getJson($base.'/tugas/'.$first->id.'/pengumpulan')->assertJsonPath('data.data.0.penilaian.nilai', '100.00');
        $recap = $this->actingAs($student->user)->getJson($base.'/rekap/'.$configId)
            ->assertJsonPath('data.data.0.nilai_akhir', 90);
        $taskComponent = collect($recap->json('data.data.0.komponen'))->firstWhere('jenis', 'tugas');
        $this->assertSame(90, $taskComponent['nilai']);
        $this->assertSame(36, $taskComponent['kontribusi']);
        $this->assertDatabaseHas('notifikasi', ['user_id' => $student->user_id, 'tipe' => 'penilaian']);
    }

    public function test_student_and_unrelated_teacher_cannot_change_submission_grades(): void
    {
        $kelas = KelasMapel::factory()->create();
        $student = $this->member($kelas);
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $grade = Penilaian::create(['siswa_id' => $student->id, 'tugas_id' => $task->id, 'nilai' => 75,
            'dinilai_oleh' => $kelas->guru_pembuat_id, 'dinilai_at' => now()]);
        $url = '/api/v1/kelas-mapel/'.$kelas->id.'/penilaian';
        $payload = ['siswa_id' => $student->id, 'tugas_id' => $task->id, 'nilai' => 100];

        $this->actingAs($student->user)->putJson($url, $payload)->assertForbidden();
        $this->actingAs(Guru::factory()->create()->user)->putJson($url, $payload)->assertForbidden();

        $this->assertSame('75.00', $grade->fresh()->nilai);
        $this->assertDatabaseCount('penilaian', 1);
        $this->assertDatabaseCount('notifikasi', 0);
    }

    public function test_submission_grades_are_not_exposed_through_another_class(): void
    {
        $kelas = KelasMapel::factory()->create();
        $task = Tugas::factory()->create();

        $this->actingAs($kelas->pembuat->user)->getJson('/api/v1/kelas-mapel/'.$kelas->id.'/tugas/'.$task->id.'/pengumpulan')->assertNotFound();
    }
}
