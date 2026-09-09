<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\KelasMapel;
use App\Models\Siswa;
use App\Models\Tugas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LearningPageTest extends TestCase
{
    use RefreshDatabase;

    private function member(KelasMapel $kelas, ?Siswa $siswa = null, string $status = 'diterima'): Siswa
    {
        $siswa ??= Siswa::factory()->create();
        $kelas->anggota()->create([
            'siswa_id' => $siswa->id, 'status' => $status,
            'join_method' => 'kode', 'requested_at' => now(),
        ]);

        return $siswa;
    }

    private function whitelist(KelasMapel $kelas, Guru $guru): void
    {
        $kelas->whitelist()->create([
            'guru_id' => $guru->id, 'ditambahkan_oleh' => $kelas->guru_pembuat_id, 'status' => 'aktif',
        ]);
    }

    public function test_teacher_dashboard_counts_only_accessible_active_classes_and_owned_requests(): void
    {
        $this->freezeTime();
        $owned = KelasMapel::factory()->create();
        $shared = KelasMapel::factory()->create();
        $archived = KelasMapel::factory()->create(['guru_pembuat_id' => $owned->guru_pembuat_id, 'status' => 'arsip']);
        $unrelated = KelasMapel::factory()->create();
        $this->whitelist($shared, $owned->pembuat);
        $student = $this->member($owned);
        $this->member($shared, $student);
        $this->member($archived);
        $this->member($unrelated);
        $pending = $this->member($owned, status: 'pending');
        $this->member($shared, status: 'pending');
        $this->member($archived, status: 'pending');
        $this->member($unrelated, status: 'pending');

        foreach ([$owned, $shared, $archived, $unrelated] as $kelas) {
            $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
            $task->pengumpulan()->create([
                'siswa_id' => $student->id, 'status' => 'dikumpulkan', 'submitted_at' => now(), 'catatan_siswa' => 'Jawaban',
            ]);
        }
        Tugas::factory()->create(['kelas_mapel_id' => $owned->id])->pengumpulan()->create([
            'siswa_id' => $student->id, 'status' => 'belum', 'submitted_at' => null,
        ]);

        $this->actingAs($owned->pembuat->user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->component('TeacherDashboard')
                ->where('stats', ['kelas' => 2, 'siswa' => 1, 'pending' => 1, 'pengumpulan' => 2])
                ->has('rooms', 2)
                ->where('rooms', fn ($rooms) => collect($rooms)->pluck('id')->sort()->values()->all() === [$owned->id, $shared->id])
                ->has('requests', 1)->where('requests.0.siswa_id', $pending->id)
                ->where('requests.0.kelas_mapel_id', $owned->id));
    }

    public function test_new_student_overviews_have_no_other_class_activities(): void
    {
        $kelas = KelasMapel::factory()->create();
        Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $kelas->jadwal()->create([
            'guru_id' => $kelas->guru_pembuat_id, 'hari' => 'senin', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00',
        ]);
        $this->actingAs(Siswa::factory()->create()->user);

        foreach (['jadwal', 'tugas'] as $section) {
            $this->get('/aktivitas/'.$section)->assertInertia(fn (Assert $page) => $page
                ->component('LearningOverview')->where('section', $section)->has('items.data', 0)->where('items.total', 0));
        }
    }

    public function test_guests_are_redirected_before_loading_learning_pages(): void
    {
        $this->get('/aktivitas/tugas')->assertRedirectToRoute('login');
        $this->get('/notifikasi')->assertRedirectToRoute('login');
        $this->get('/data-sekolah')->assertRedirectToRoute('login');
    }

    public function test_owner_receives_management_permissions_and_can_open_settings(): void
    {
        $kelas = KelasMapel::factory()->create();

        $this->actingAs($kelas->pembuat->user)->get('/mata-pelajaran/'.$kelas->id.'/pengaturan')
            ->assertInertia(fn (Assert $page) => $page->component('SubjectTasks')->where('section', 'pengaturan')
                ->where('kelasMapel.id', $kelas->id)->where('permissions', [
                    'manageAcademic' => true, 'reviewMembers' => true, 'update' => true,
                    'submit' => false, 'viewRekap' => true, 'isTeacher' => true,
                ]));
        $this->get('/data-sekolah')->assertInertia(fn (Assert $page) => $page->component('MasterData'));
    }

    public function test_whitelisted_mapel_teacher_can_teach_but_cannot_open_owner_settings(): void
    {
        $kelas = KelasMapel::factory()->create();
        $guru = Guru::factory()->create();
        $this->whitelist($kelas, $guru);

        $this->actingAs($guru->user)->get('/mata-pelajaran/'.$kelas->id.'/rekap')
            ->assertInertia(fn (Assert $page) => $page->component('SubjectTasks')->where('section', 'rekap')
                ->where('permissions', [
                    'manageAcademic' => true, 'reviewMembers' => false, 'update' => false,
                    'submit' => false, 'viewRekap' => true, 'isTeacher' => true,
                ])->missing('kelasMapel.kode_kelas')->missing('kelasMapel.invite_token'));
        $this->get('/mata-pelajaran/'.$kelas->id.'/pengaturan')->assertForbidden();
    }

    public function test_piket_can_monitor_members_but_cannot_open_rekap_or_school_data(): void
    {
        $kelas = KelasMapel::factory()->create();
        $guru = Guru::factory()->create(['jenis_guru' => 'guru_piket']);
        $this->whitelist($kelas, $guru);

        $this->actingAs($guru->user)->get('/mata-pelajaran/'.$kelas->id.'/anggota')
            ->assertInertia(fn (Assert $page) => $page->component('SubjectTasks')->where('section', 'anggota')
                ->where('permissions', [
                    'manageAcademic' => false, 'reviewMembers' => false, 'update' => false,
                    'submit' => false, 'viewRekap' => false, 'isTeacher' => true,
                ]));
        $this->get('/mata-pelajaran/'.$kelas->id.'/rekap')->assertForbidden();
        $this->get('/mata-pelajaran/'.$kelas->id.'/pengaturan')->assertForbidden();
        $this->get('/data-sekolah')->assertForbidden();
    }

    public function test_student_can_open_own_class_rekap_but_not_members_or_settings(): void
    {
        $kelas = KelasMapel::factory()->create();
        $student = $this->member($kelas);

        $this->actingAs($student->user)->get('/mata-pelajaran/'.$kelas->id.'/rekap')
            ->assertInertia(fn (Assert $page) => $page->component('SubjectTasks')->where('section', 'rekap')
                ->where('permissions', [
                    'manageAcademic' => false, 'reviewMembers' => false, 'update' => false,
                    'submit' => true, 'viewRekap' => true, 'isTeacher' => false,
                ])->missing('kelasMapel.kode_kelas')->missing('kelasMapel.invite_token'));
        $this->get('/mata-pelajaran/'.$kelas->id.'/anggota')->assertForbidden();
        $this->get('/mata-pelajaran/'.$kelas->id.'/pengaturan')->assertForbidden();
        $this->get('/data-sekolah')->assertForbidden();
    }

    public function test_nonmember_and_revoked_teacher_receive_403_for_classroom_pages(): void
    {
        $kelas = KelasMapel::factory()->create();
        $guru = Guru::factory()->create();
        $this->whitelist($kelas, $guru);
        $kelas->whitelist()->where('guru_id', $guru->id)->update(['status' => 'nonaktif']);

        $this->actingAs($guru->user)->get('/mata-pelajaran/'.$kelas->id.'/tugas')->assertForbidden();
        $this->actingAs(Siswa::factory()->create()->user)->get('/mata-pelajaran/'.$kelas->id.'/nilai')->assertForbidden();
    }

    public function test_archived_class_remains_read_only_for_teacher(): void
    {
        $kelas = KelasMapel::factory()->create(['status' => 'arsip']);

        $this->actingAs($kelas->pembuat->user)->get('/mata-pelajaran/'.$kelas->id.'/tugas')
            ->assertInertia(fn (Assert $page) => $page->component('SubjectTasks')
                ->where('kelasMapel.status', 'arsip')->where('permissions.manageAcademic', false)
                ->where('permissions.reviewMembers', false)->where('permissions.submit', false)
                ->where('permissions.update', true));
    }

    public function test_unknown_activity_and_classroom_tabs_return_404(): void
    {
        $kelas = KelasMapel::factory()->create();
        $this->actingAs($kelas->pembuat->user);

        $this->get('/aktivitas/tidak-ada')->assertNotFound();
        $this->get('/mata-pelajaran/'.$kelas->id.'/tidak-ada')->assertNotFound();
    }

    public function test_invitation_renders_join_page_and_remembers_link_for_login(): void
    {
        $kelas = KelasMapel::factory()->create();
        $path = '/kelas-mapel/undangan/'.$kelas->invite_token;

        $this->get($path)->assertSessionHas('kelas_invite', $path)
            ->assertInertia(fn (Assert $page) => $page->component('JoinClass')
                ->where('invitation.id', $kelas->id)->where('invitation.mapel_id', $kelas->mapel_id)
                ->where('invitation.token', $kelas->invite_token)->where('invitation.guru', $kelas->pembuat->nama_lengkap)
                ->where('pendingInvite', $path)->missing('invitation.kode_kelas'));
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('pendingInvite', $path));
        $this->assertDatabaseCount('anggota_kelas', 0);
    }

    public function test_json_invitation_keeps_public_contract_without_starting_membership(): void
    {
        $kelas = KelasMapel::factory()->create();

        $this->getJson('/kelas-mapel/undangan/'.$kelas->invite_token)->assertOk()->assertExactJson([
            'data' => ['id' => $kelas->id, 'mapel_id' => $kelas->mapel_id,
                'nama_kelas_mapel' => $kelas->nama_kelas_mapel, 'deskripsi' => null],
            'message' => 'Masuk ke akun siswa lalu ajukan bergabung dengan invite_token ini. Persetujuan guru tetap diperlukan.',
        ])->assertSessionMissing('kelas_invite');
        $this->assertDatabaseCount('anggota_kelas', 0);
    }

    public function test_archived_invitation_returns_404_without_remembering_link(): void
    {
        $kelas = KelasMapel::factory()->create(['status' => 'arsip']);

        $this->get('/kelas-mapel/undangan/'.$kelas->invite_token)->assertNotFound()->assertSessionMissing('kelas_invite');
    }

    public function test_submission_list_exposes_file_presence_without_private_storage_path(): void
    {
        $this->freezeTime();
        $kelas = KelasMapel::factory()->create();
        $student = $this->member($kelas);
        $other = $this->member($kelas);
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $task->pengumpulan()->create([
            'siswa_id' => $student->id, 'status' => 'dikumpulkan', 'submitted_at' => now(),
            'file_path' => 'pengumpulan/private/jawaban-rahasia.pdf',
        ]);
        $task->pengumpulan()->create([
            'siswa_id' => $other->id, 'status' => 'dikumpulkan', 'submitted_at' => now()->subMinute(),
            'catatan_siswa' => 'Jawaban teks',
        ]);

        $this->actingAs($kelas->pembuat->user)->getJson('/api/v1/kelas-mapel/'.$kelas->id.'/tugas/'.$task->id.'/pengumpulan')
            ->assertJsonCount(2, 'data.data')->assertJsonPath('data.data.0.has_file', true)
            ->assertJsonPath('data.data.1.has_file', false)
            ->assertJsonMissingPath('data.data.0.file_path')->assertJsonMissingPath('data.data.1.file_path')
            ->assertDontSee('jawaban-rahasia.pdf');
    }

    public function test_manual_rekap_note_is_returned_for_editing_and_survives_score_update(): void
    {
        $kelas = KelasMapel::factory()->create();
        $student = $this->member($kelas);
        $config = $kelas->konfigurasiRekap()->create([
            'guru_id' => $kelas->guru_pembuat_id, 'nama_konfigurasi' => 'Rekap semester', 'status' => 'aktif',
        ]);
        $component = $config->komponen()->create(['jenis' => 'US', 'bobot' => 100, 'metode' => 'manual']);
        $base = '/api/v1/kelas-mapel/'.$kelas->id.'/rekap/'.$config->id;

        $this->actingAs($kelas->pembuat->user)->putJson($base.'/komponen/'.$component->id.'/manual', [
            'siswa_id' => $student->id, 'nilai' => 75, 'catatan' => 'Perbaiki langkah perhitungan.',
        ])->assertOk();
        $response = $this->getJson($base)->assertJsonPath('data.data.0.komponen.0.catatan', 'Perbaiki langkah perhitungan.');
        $this->putJson($base.'/komponen/'.$component->id.'/manual', [
            'siswa_id' => $student->id, 'nilai' => 0, 'catatan' => $response->json('data.data.0.komponen.0.catatan'),
        ])->assertOk()->assertJsonPath('data.nilai', '0.00');

        $this->assertDatabaseHas('nilai_rekap_manual', [
            'komponen_rekap_id' => $component->id, 'siswa_id' => $student->id,
            'nilai' => 0, 'catatan' => 'Perbaiki langkah perhitungan.',
        ]);
        $this->actingAs($student->user)->getJson($base)->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.komponen.0.catatan', 'Perbaiki langkah perhitungan.')
            ->assertJsonPath('data.data.0.nilai_akhir', 0);
    }
}
