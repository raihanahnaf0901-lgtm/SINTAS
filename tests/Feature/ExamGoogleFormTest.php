<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\KelasMapel;
use App\Models\Penilaian;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\Ujian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ExamGoogleFormTest extends TestCase
{
    use RefreshDatabase;

    private function member(KelasMapel $kelas, string $status = 'diterima'): Siswa
    {
        $student = Siswa::factory()->create();
        $kelas->anggota()->create(['siswa_id' => $student->id, 'status' => $status,
            'join_method' => 'kode', 'requested_at' => now()]);

        return $student;
    }

    private function examPayload(array $overrides = []): array
    {
        return [...['judul' => 'Ulangan harian PIPAS', 'jenis_ujian' => 'UH', 'tanggal' => '2026-10-01'], ...$overrides];
    }

    #[TestWith(['https://forms.gle/Ulangan_1-test'])]
    #[TestWith(['https://docs.google.com/forms/d/e/1FAIpQLTest_123/viewform?usp=sf_link'])]
    #[TestWith(['https://docs.google.com/forms/d/Exam_123/viewform'])]
    #[TestWith(['https://docs.google.com/forms/u/0/d/e/Exam_123/viewform/'])]
    public function test_teacher_can_create_an_exam_with_a_google_form_respondent_link(string $url): void
    {
        $kelas = KelasMapel::factory()->create();

        $id = $this->actingAs($kelas->pembuat->user)->postJson('/api/v1/kelas-mapel/'.$kelas->id.'/ujian',
            $this->examPayload(['google_form_url' => $url, 'guru_id' => 999, 'kelas_mapel_id' => 999]))
            ->assertCreated()->assertJsonPath('data.google_form_url', $url)->json('data.id');

        $this->assertDatabaseHas('ujian', ['id' => $id, 'google_form_url' => $url,
            'guru_id' => $kelas->guru_pembuat_id, 'kelas_mapel_id' => $kelas->id]);
    }

    #[TestWith(['https://docs.google.com/forms/d/Exam123/edit'])]
    #[TestWith(['https://docs.google.com/document/d/Exam123/viewform'])]
    #[TestWith(['https://example.com/forms/d/Exam123/viewform'])]
    #[TestWith(['https://docs.google.com.example.com/forms/d/Exam123/viewform'])]
    #[TestWith(['https://forms.gle/'])]
    #[TestWith(['https://forms.gle/Exam123/extra'])]
    #[TestWith(['https://teacher@forms.gle/Exam123'])]
    #[TestWith(['https://forms.gle:8443/Exam123'])]
    public function test_non_respondent_links_return_422_without_creating_an_exam(string $url): void
    {
        $kelas = KelasMapel::factory()->create();
        $this->member($kelas);

        $this->actingAs($kelas->pembuat->user)->postJson('/api/v1/kelas-mapel/'.$kelas->id.'/ujian',
            $this->examPayload(['google_form_url' => $url]))->assertUnprocessable()
            ->assertJsonPath('errors.google_form_url.0', 'Gunakan link responden Google Form dari forms.gle atau docs.google.com/forms/.../viewform, bukan link edit.');

        $this->assertDatabaseCount('ujian', 0);
        $this->assertDatabaseCount('notifikasi', 0);
    }

    public static function malformedLinks(): array
    {
        return [
            'insecure http' => ['http://forms.gle/Exam123'],
            'not a string' => [['https://forms.gle/Exam123']],
            'too long' => ['https://forms.gle/'.str_repeat('a', 2048)],
        ];
    }

    #[DataProvider('malformedLinks')]
    public function test_malformed_google_form_values_return_422(mixed $url): void
    {
        $kelas = KelasMapel::factory()->create();

        $this->actingAs($kelas->pembuat->user)->postJson('/api/v1/kelas-mapel/'.$kelas->id.'/ujian',
            $this->examPayload(['google_form_url' => $url]))->assertUnprocessable()->assertJsonValidationErrors('google_form_url');

        $this->assertDatabaseCount('ujian', 0);
    }

    public function test_exam_link_can_be_added_edited_or_cleared_without_being_erased_by_omitted_input(): void
    {
        $kelas = KelasMapel::factory()->create();
        $exam = Ujian::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $url = '/api/v1/kelas-mapel/'.$kelas->id.'/ujian/'.$exam->id;
        $this->actingAs($kelas->pembuat->user);

        $this->patchJson($url, $this->examPayload(['google_form_url' => 'https://forms.gle/FirstExam']))
            ->assertOk()->assertJsonPath('data.google_form_url', 'https://forms.gle/FirstExam');
        $this->patchJson($url, $this->examPayload(['judul' => 'Judul diperbarui']))
            ->assertOk()->assertJsonPath('data.google_form_url', 'https://forms.gle/FirstExam');
        $this->patchJson($url, $this->examPayload(['google_form_url' => 'https://forms.gle/RevisedExam']))
            ->assertOk()->assertJsonPath('data.google_form_url', 'https://forms.gle/RevisedExam');
        $this->assertDatabaseHas('ujian', ['id' => $exam->id, 'google_form_url' => 'https://forms.gle/RevisedExam']);

        $this->patchJson($url, $this->examPayload(['google_form_url' => '']))
            ->assertOk()->assertJsonPath('data.google_form_url', null);

        $this->assertNull($exam->fresh()->google_form_url);
        $this->assertDatabaseCount('ujian', 1);
    }

    public function test_edit_rejects_an_editor_link_with_422_and_preserves_the_saved_exam(): void
    {
        $kelas = KelasMapel::factory()->create();
        $exam = Ujian::factory()->create(['kelas_mapel_id' => $kelas->id, 'google_form_url' => 'https://forms.gle/OriginalExam']);

        $this->actingAs($kelas->pembuat->user)->patchJson('/api/v1/kelas-mapel/'.$kelas->id.'/ujian/'.$exam->id,
            $this->examPayload(['google_form_url' => 'https://docs.google.com/forms/d/Exam123/edit']))
            ->assertUnprocessable()->assertJsonValidationErrors('google_form_url');

        $this->assertDatabaseHas('ujian', ['id' => $exam->id, 'judul' => $exam->judul, 'google_form_url' => 'https://forms.gle/OriginalExam']);
        $this->assertDatabaseCount('notifikasi', 0);
    }

    public function test_grading_list_only_contains_accepted_students_and_grades_for_the_selected_exam(): void
    {
        $this->freezeTime();
        $kelas = KelasMapel::factory()->create();
        $first = $this->member($kelas);
        $second = $this->member($kelas);
        $ungraded = $this->member($kelas);
        $this->member($kelas, 'pending');
        $this->member($kelas, 'ditolak');
        Siswa::factory()->create();
        $exam = Ujian::factory()->create(['kelas_mapel_id' => $kelas->id, 'google_form_url' => 'https://forms.gle/Exam123']);
        $otherExam = Ujian::factory()->create(['kelas_mapel_id' => $kelas->id]);
        foreach ([[$first, $exam, 0], [$second, $exam, 85], [$first, $otherExam, 40]] as [$student, $target, $score]) {
            Penilaian::create(['siswa_id' => $student->id, 'ujian_id' => $target->id, 'nilai' => $score,
                'dinilai_oleh' => $kelas->guru_pembuat_id, 'dinilai_at' => now()]);
        }
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        Penilaian::create(['siswa_id' => $ungraded->id, 'tugas_id' => $task->id, 'nilai' => 100,
            'dinilai_oleh' => $kelas->guru_pembuat_id, 'dinilai_at' => now()]);
        $base = '/api/v1/kelas-mapel/'.$kelas->id.'/ujian';

        $response = $this->actingAs($kelas->pembuat->user)->getJson($base.'/'.$exam->id.'/penilaian')
            ->assertOk()->assertJsonCount(3, 'data.data')->assertJsonPath('data.total', 3);

        $records = collect($response->json('data.data'))->keyBy('id');
        $this->assertSame('0.00', $records[$first->id]['penilaian']['nilai']);
        $this->assertSame($kelas->pembuat->nama_lengkap, $records[$first->id]['penilaian']['penilai']['nama_lengkap']);
        $this->assertSame('85.00', $records[$second->id]['penilaian']['nilai']);
        $this->assertNull($records[$ungraded->id]['penilaian']);
        $this->assertArrayNotHasKey('user_id', $records[$first->id]);

        $studentResponse = $this->actingAs($first->user)->getJson($base)->assertOk();
        $studentExam = collect($studentResponse->json('data.data'))->firstWhere('id', $exam->id);
        $this->assertSame('https://forms.gle/Exam123', $studentExam['google_form_url']);
        $this->assertCount(1, $studentExam['penilaian']);
        $this->assertSame($first->id, $studentExam['penilaian'][0]['siswa_id']);
        $this->assertSame('0.00', $studentExam['penilaian'][0]['nilai']);
        $this->assertSame($kelas->pembuat->nama_lengkap, $studentExam['penilaian'][0]['penilai']['nama_lengkap']);
    }

    public function test_exam_grading_list_paginates_accepted_students_without_losing_the_last_grade(): void
    {
        $kelas = KelasMapel::factory()->create();
        $exam = Ujian::factory()->create(['kelas_mapel_id' => $kelas->id]);
        foreach (range(1, 51) as $number) {
            $student = $this->member($kelas);
            $student->update(['nama_lengkap' => sprintf('Siswa %02d', $number)]);
        }
        Penilaian::create(['siswa_id' => $student->id, 'ujian_id' => $exam->id, 'nilai' => 92.25,
            'dinilai_oleh' => $kelas->guru_pembuat_id, 'dinilai_at' => now()]);
        $url = '/api/v1/kelas-mapel/'.$kelas->id.'/ujian/'.$exam->id.'/penilaian';

        $this->actingAs($kelas->pembuat->user)->getJson($url)->assertJsonCount(50, 'data.data')
            ->assertJsonPath('data.total', 51)->assertJsonPath('data.last_page', 2);
        $this->getJson($url.'?page=2')->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $student->id)->assertJsonPath('data.data.0.penilaian.nilai', '92.25');
    }

    public function test_unauthenticated_exam_grading_list_returns_401(): void
    {
        $exam = Ujian::factory()->create();

        $this->getJson('/api/v1/kelas-mapel/'.$exam->kelas_mapel_id.'/ujian/'.$exam->id.'/penilaian')->assertUnauthorized();
    }

    #[TestWith(['student'])]
    #[TestWith(['unrelated_teacher'])]
    #[TestWith(['piket'])]
    #[TestWith(['archived_owner'])]
    public function test_unauthorized_accounts_cannot_list_or_write_exam_grades_and_receive_403(string $role): void
    {
        $kelas = KelasMapel::factory()->create(['status' => $role === 'archived_owner' ? 'arsip' : 'aktif']);
        $student = $this->member($kelas);
        $exam = Ujian::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $actor = match ($role) {
            'student' => $student->user,
            'archived_owner' => $kelas->pembuat->user,
            default => Guru::factory()->create(['jenis_guru' => $role === 'piket' ? 'guru_piket' : 'guru_mapel'])->user,
        };
        if ($role === 'piket') {
            $kelas->whitelist()->create(['guru_id' => $actor->guru->id, 'ditambahkan_oleh' => $kelas->guru_pembuat_id, 'status' => 'aktif']);
        }
        $base = '/api/v1/kelas-mapel/'.$kelas->id;

        $this->actingAs($actor)->getJson($base.'/ujian/'.$exam->id.'/penilaian')->assertForbidden();
        $this->putJson($base.'/penilaian', ['siswa_id' => $student->id, 'ujian_id' => $exam->id, 'nilai' => 100])->assertForbidden();

        $this->assertDatabaseCount('penilaian', 0);
        $this->assertDatabaseCount('notifikasi', 0);
    }

    public function test_exam_grades_cannot_be_read_or_edited_through_another_class(): void
    {
        $kelas = KelasMapel::factory()->create();
        $exam = Ujian::factory()->create(['google_form_url' => 'https://forms.gle/PrivateExam']);
        $base = '/api/v1/kelas-mapel/'.$kelas->id.'/ujian/'.$exam->id;

        $this->actingAs($kelas->pembuat->user)->getJson($base.'/penilaian')->assertNotFound();
        $this->patchJson($base, $this->examPayload(['google_form_url' => 'https://forms.gle/ChangedExam']))->assertNotFound();

        $this->assertDatabaseHas('ujian', ['id' => $exam->id, 'google_form_url' => 'https://forms.gle/PrivateExam']);
    }

    public function test_authorized_teacher_can_enter_and_edit_exam_scores_and_recalculate_the_semester_recap(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 3)->startOfDay());
        $kelas = KelasMapel::factory()->create();
        $student = $this->member($kelas);
        $pending = $this->member($kelas, 'pending');
        $teacher = Guru::factory()->create();
        $kelas->whitelist()->create(['guru_id' => $teacher->id, 'ditambahkan_oleh' => $kelas->guru_pembuat_id, 'status' => 'aktif']);
        $semesterExam = Ujian::factory()->create(['kelas_mapel_id' => $kelas->id, 'jenis_ujian' => 'US', 'tanggal' => '2026-10-02']);
        $base = '/api/v1/kelas-mapel/'.$kelas->id;
        $this->actingAs($teacher->user);
        $examId = $this->postJson($base.'/ujian', $this->examPayload(['google_form_url' => 'https://forms.gle/CompletedExam']))
            ->assertCreated()->assertJsonPath('data.guru_id', $teacher->id)->json('data.id');
        $configId = $this->postJson($base.'/rekap', ['nama_konfigurasi' => 'Akhir semester', 'status' => 'aktif', 'komponen' => [
            ['jenis' => 'UH', 'bobot' => 40, 'metode' => 'rata_rata'],
            ['jenis' => 'US', 'bobot' => 60, 'metode' => 'rata_rata'],
        ]])->assertCreated()->json('data.id');
        $gradeId = $this->putJson($base.'/penilaian', ['siswa_id' => $student->id, 'ujian_id' => $examId, 'nilai' => 0])
            ->assertOk()->assertJsonPath('data.nilai', '0.00')->assertJsonPath('data.dinilai_oleh', $teacher->id)->json('data.id');
        $this->putJson($base.'/penilaian', ['siswa_id' => $student->id, 'ujian_id' => $semesterExam->id, 'nilai' => 80])->assertOk();
        $this->getJson($base.'/rekap/'.$configId)->assertJsonPath('data.data.0.nilai_akhir', 48);

        $this->putJson($base.'/penilaian', ['siswa_id' => $student->id, 'ujian_id' => $examId, 'nilai' => 82.5, 'catatan' => 'Hasil pemeriksaan Google Form'])
            ->assertOk()->assertJsonPath('data.id', $gradeId)->assertJsonPath('data.nilai', '82.50');

        $this->assertDatabaseCount('penilaian', 2);
        $this->assertDatabaseHas('penilaian', ['id' => $gradeId, 'ujian_id' => $examId, 'tugas_id' => null,
            'nilai' => 82.5, 'dinilai_oleh' => $teacher->id, 'catatan' => 'Hasil pemeriksaan Google Form']);
        $this->getJson($base.'/ujian/'.$examId.'/penilaian')->assertJsonPath('data.data.0.penilaian.nilai', '82.50');
        $this->actingAs($student->user)->getJson($base.'/rekap/'.$configId)
            ->assertJsonPath('data.data.0.nilai_akhir', 81)->assertJsonPath('data.data.0.lengkap', true);
        $this->getJson($base.'/penilaian')->assertJsonFragment(['id' => $gradeId, 'nilai' => '82.50']);
        $this->assertDatabaseHas('notifikasi', ['user_id' => $student->user_id, 'tipe' => 'ujian', 'pesan' => 'Ulangan harian PIPAS']);
        $this->assertDatabaseHas('notifikasi', ['user_id' => $student->user_id, 'tipe' => 'penilaian']);
        $this->assertDatabaseMissing('notifikasi', ['user_id' => $pending->user_id]);
        $this->actingAs($pending->user)->getJson($base.'/ujian')->assertForbidden();
    }
}
