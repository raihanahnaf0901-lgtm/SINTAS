<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\KelasMapel;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\Tugas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SchoolAcademicAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_without_school_cannot_create_a_class(): void
    {
        $guru = Guru::factory()->create();

        $this->actingAs($guru->user)->postJson('/api/v1/kelas-mapel', ['nama_kelas_mapel' => 'Matematika X'])
            ->assertUnprocessable()->assertJsonValidationErrors([
                'sekolah' => 'Bergabung dan tunggu persetujuan sekolah sebelum membuat kelas.',
            ]);
        $this->assertDatabaseCount('kelas_mapel', 0);
        $this->assertDatabaseCount('mapel', 0);
    }

    #[TestWith(['pending'])]
    #[TestWith(['ditolak'])]
    public function test_unapproved_membership_cannot_create_a_class(string $status): void
    {
        $guru = Guru::factory()->inSchool()->create();
        $guru->keanggotaanSekolah()->update(['status' => $status]);

        $this->actingAs($guru->user)->postJson('/api/v1/kelas-mapel', ['nama_kelas_mapel' => 'Matematika X'])
            ->assertUnprocessable()->assertJsonValidationErrors('sekolah');
        $this->assertDatabaseCount('kelas_mapel', 0);
    }

    public function test_expired_subscription_blocks_only_new_classes_not_existing_academic_work(): void
    {
        $this->freezeTime();
        $guru = Guru::factory()->inSchool()->create();
        $school = $guru->sekolahAktif();
        $school->update(['subscription_ends_at' => now()->subSecond()]);
        $room = KelasMapel::factory()->create(['guru_pembuat_id' => $guru->id, 'sekolah_id' => $school->id]);

        $this->actingAs($guru->user)->postJson('/api/v1/kelas-mapel', ['nama_kelas_mapel' => 'Kelas baru'])
            ->assertUnprocessable()->assertJsonValidationErrors([
                'sekolah' => 'Langganan sekolah harus aktif sebelum membuat kelas baru.',
            ]);
        $this->assertDatabaseCount('kelas_mapel', 1);
        $this->assertTrue($guru->user->can('manageAcademic', $room));
        $this->patchJson('/api/v1/kelas-mapel/'.$room->id, ['nama_kelas_mapel' => 'Kelas diperbarui'])
            ->assertOk()->assertJsonPath('data.nama_kelas_mapel', 'Kelas diperbarui');
        $this->assertDatabaseHas('kelas_mapel', ['id' => $room->id, 'nama_kelas_mapel' => 'Kelas diperbarui']);
    }

    public function test_school_is_assigned_server_side_and_injection_is_rejected(): void
    {
        $guru = Guru::factory()->inSchool()->create();
        $otherSchool = Sekolah::factory()->create();

        $this->actingAs($guru->user)->postJson('/api/v1/kelas-mapel', [
            'nama_kelas_mapel' => 'Matematika X', 'sekolah_id' => $otherSchool->id,
        ])->assertNotFound();
        $this->assertDatabaseCount('kelas_mapel', 0);
        $id = $this->postJson('/api/v1/kelas-mapel', ['nama_kelas_mapel' => 'Matematika X'])
            ->assertCreated()->assertJsonPath('data.sekolah_id', $guru->sekolahAktif()->id)->json('data.id');
        $this->assertDatabaseHas('kelas_mapel', [
            'id' => $id, 'sekolah_id' => $guru->sekolahAktif()->id, 'guru_pembuat_id' => $guru->id,
        ]);
    }

    public function test_inactive_school_blocks_new_classes(): void
    {
        $guru = Guru::factory()->inSchool()->create();
        $guru->sekolahAktif()->update(['status' => 'nonaktif']);

        $this->actingAs($guru->user)->postJson('/api/v1/kelas-mapel', ['nama_kelas_mapel' => 'Matematika X'])
            ->assertUnprocessable()->assertJsonValidationErrors('sekolah');
        $this->assertDatabaseCount('kelas_mapel', 0);
    }

    public function test_school_admin_manages_own_school_rooms_without_needing_an_explicit_whitelist_entry(): void
    {
        $school = Sekolah::factory()->withAdmin()->create();
        $teacher = Guru::factory()->inSchool($school)->create();
        $room = KelasMapel::factory()->create(['guru_pembuat_id' => $teacher->id, 'sekolah_id' => $school->id]);
        $unrelated = KelasMapel::factory()->create(['sekolah_id' => Sekolah::factory()->create()->id]);
        $legacy = KelasMapel::factory()->create();
        $task = Tugas::factory()->create(['kelas_mapel_id' => $room->id]);
        $user = $school->admin->user;

        $this->assertTrue($user->can('view', $room));
        foreach (['manageAcademic', 'update', 'reviewMembers', 'viewRekap'] as $ability) {
            $this->assertTrue($user->can($ability, $room), $ability);
        }
        $this->assertFalse($user->can('submit', $room));
        $this->actingAs($user)->getJson('/api/v1/kelas-mapel')
            ->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $room->id);
        $this->getJson('/api/v1/kelas-mapel/'.$room->id)->assertOk()->assertJsonPath('data.kode_kelas', $room->kode_kelas);
        $this->getJson('/api/v1/kelas-mapel/'.$room->id.'/tugas/'.$task->id.'/pengumpulan')->assertOk();
        $this->get('/mata-pelajaran/'.$room->id)->assertInertia(fn (Assert $page) => $page
            ->component('SubjectTasks')->where('permissions.manageAcademic', true)->where('permissions.update', true));
        $this->getJson('/api/v1/kelas-mapel/'.$unrelated->id)->assertForbidden();
        $this->getJson('/api/v1/kelas-mapel/'.$legacy->id)->assertForbidden();
        $this->postJson('/api/v1/kelas-mapel/'.$unrelated->id.'/tugas', [])->assertForbidden();
        $this->putJson('/api/v1/kelas-mapel/'.$unrelated->id.'/penilaian', [])->assertForbidden();
        $this->assertDatabaseMissing('whitelist_guru_kelas', ['kelas_mapel_id' => $room->id, 'guru_id' => $school->admin_guru_id]);
        $this->assertDatabaseCount('tugas', 1);
        $this->assertDatabaseCount('penilaian', 0);
    }

    public function test_explicit_same_school_whitelist_retains_teaching_and_piket_remains_read_only(): void
    {
        $school = Sekolah::factory()->withAdmin()->create();
        $teacher = Guru::factory()->inSchool($school)->create();
        $room = KelasMapel::factory()->create(['guru_pembuat_id' => $teacher->id, 'sekolah_id' => $school->id]);
        $colleague = Guru::factory()->inSchool($school)->create();

        $this->actingAs($teacher->user)->postJson('/api/v1/kelas-mapel/'.$room->id.'/whitelist', [
            'guru_id' => $colleague->id, 'status' => 'aktif',
        ])->assertOk();
        $this->assertTrue($colleague->user->can('manageAcademic', $room));
        $colleague->keanggotaanSekolah()->where('sekolah_id', $school->id)->update(['jenis_guru' => 'guru_piket']);
        $user = $colleague->user->fresh();
        $this->assertTrue($user->can('view', $room));
        $this->assertFalse($user->can('manageAcademic', $room));
        $this->assertFalse($user->can('create', [KelasMapel::class, $school]));
    }

    public function test_cross_school_whitelist_grants_and_stale_entries_cannot_bypass_school_isolation(): void
    {
        $teacher = Guru::factory()->inSchool()->create();
        $outsider = Guru::factory()->inSchool()->create();
        $room = KelasMapel::factory()->create([
            'guru_pembuat_id' => $teacher->id, 'sekolah_id' => $teacher->sekolahAktif()->id,
        ]);

        $this->actingAs($teacher->user)->postJson('/api/v1/kelas-mapel/'.$room->id.'/whitelist', [
            'guru_id' => $outsider->id, 'status' => 'aktif',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'guru_id' => 'Guru harus sudah diterima di sekolah yang sama.',
        ]);
        $this->assertDatabaseMissing('whitelist_guru_kelas', ['kelas_mapel_id' => $room->id, 'guru_id' => $outsider->id]);
        $room->whitelist()->create(['guru_id' => $outsider->id, 'ditambahkan_oleh' => $teacher->id, 'status' => 'aktif']);
        $this->actingAs($outsider->user)->getJson('/api/v1/kelas-mapel/'.$room->id)->assertForbidden();
        $this->getJson('/api/v1/kelas-mapel')->assertJsonCount(0, 'data.data');
    }

    public function test_ordinary_teacher_does_not_inherit_schoolwide_access_and_loses_whitelist_access_if_membership_is_revoked(): void
    {
        $teacher = Guru::factory()->inSchool()->create();
        $colleague = Guru::factory()->inSchool($teacher->sekolahAktif())->create();
        $room = KelasMapel::factory()->create([
            'guru_pembuat_id' => $teacher->id, 'sekolah_id' => $teacher->sekolahAktif()->id,
        ]);

        $this->actingAs($colleague->user)->getJson('/api/v1/kelas-mapel/'.$room->id)->assertForbidden();
        $this->actingAs($teacher->user)->postJson('/api/v1/kelas-mapel/'.$room->id.'/whitelist', [
            'guru_id' => $colleague->id, 'status' => 'aktif',
        ])->assertOk();
        $this->actingAs($colleague->user)->getJson('/api/v1/kelas-mapel/'.$room->id)->assertOk();
        $colleague->keanggotaanSekolah()->update(['status' => 'ditolak']);
        $this->getJson('/api/v1/kelas-mapel/'.$room->id)->assertForbidden();
        $this->assertFalse($colleague->user->can('manageAcademic', $room));
    }

    public function test_master_data_exposes_only_accepted_teachers_from_own_school(): void
    {
        $school = Sekolah::factory()->create();
        $teacher = Guru::factory()->inSchool($school)->create();
        $colleague = Guru::factory()->inSchool($school)->create();
        $pending = Guru::factory()->inSchool($school)->create();
        $pending->keanggotaanSekolah()->update(['status' => 'pending']);
        Guru::factory()->inSchool()->create();

        $response = $this->actingAs($teacher->user)->getJson('/api/v1/master-data')->assertJsonCount(2, 'guru');
        $this->assertSame(
            [$teacher->id, $colleague->id],
            collect($response->json('guru'))->pluck('id')->sort()->values()->all(),
        );
        $this->actingAs(Guru::factory()->create()->user)->getJson('/api/v1/master-data')->assertJsonCount(0, 'guru');
        $this->actingAs(Siswa::factory()->create()->user)->getJson('/api/v1/master-data')->assertJsonCount(0, 'guru');
    }

    public function test_legacy_classroom_whitelist_keeps_existing_access_without_becoming_school_property(): void
    {
        $room = KelasMapel::factory()->create();
        $user = $room->pembuat->user;

        $this->actingAs($user)->getJson('/api/v1/kelas-mapel/'.$room->id)->assertOk();
        $this->assertTrue($user->can('manageAcademic', $room));
        $this->assertFalse($user->can('create', KelasMapel::class));
        $this->assertNull($room->fresh()->sekolah_id);
    }

    public function test_academic_write_uses_school_role_instead_of_a_loaded_global_teacher_profile(): void
    {
        $this->freezeTime();
        $teacher = Guru::factory()->inSchool()->create();
        $room = KelasMapel::factory()->create([
            'guru_pembuat_id' => $teacher->id, 'sekolah_id' => $teacher->sekolahAktif()->id,
        ]);
        $user = $teacher->user->load('guru');
        $teacher->keanggotaanSekolah()->where('sekolah_id', $room->sekolah_id)->update(['jenis_guru' => 'guru_piket']);

        $this->actingAs($user)->postJson('/api/v1/kelas-mapel/'.$room->id.'/tugas', [
            'judul' => 'Tugas setelah pencabutan akses', 'jenis' => 'tugas',
            'deadline' => now()->addDay()->toDateTimeString(),
        ])->assertForbidden();
        $this->assertDatabaseCount('tugas', 0);
        $this->assertDatabaseCount('notifikasi', 0);
    }

    #[TestWith(['guru_mapel'])]
    #[TestWith(['guru_piket'])]
    public function test_founder_can_manage_class_settings_students_tasks_exams_and_rekap_regardless_of_teacher_type(string $jenisGuru): void
    {
        $this->freezeTime();
        $school = Sekolah::factory()->withAdmin()->create();
        $admin = $school->admin;
        $admin->update(['jenis_guru' => $jenisGuru]);
        $admin->keanggotaanSekolah()->where('sekolah_id', $school->id)->update(['jenis_guru' => $jenisGuru]);
        $teacher = Guru::factory()->inSchool($school)->create();
        $room = KelasMapel::factory()->create(['guru_pembuat_id' => $teacher->id, 'sekolah_id' => $school->id]);
        $student = Siswa::factory()->create();
        $member = $room->anggota()->create([
            'siswa_id' => $student->id, 'status' => 'pending', 'join_method' => 'kode', 'requested_at' => now(),
        ]);
        $base = '/api/v1/kelas-mapel/'.$room->id;

        $this->actingAs($admin->user->fresh())->get('/mata-pelajaran/'.$room->id.'/rekap')
            ->assertInertia(fn (Assert $page) => $page->component('SubjectTasks')
                ->where('permissions.viewRekap', true)->where('permissions.manageAcademic', true));
        $this->get('/mata-pelajaran/'.$room->id.'/pengaturan')->assertOk();
        $this->patchJson($base, ['nama_kelas_mapel' => 'Kelas ditinjau admin', 'kode_kelas' => 'ADMIN-123'])
            ->assertOk()->assertJsonPath('data.kode_kelas', 'ADMIN-123');
        $this->patchJson($base.'/anggota/'.$member->id, ['status' => 'diterima'])->assertOk();
        $taskId = $this->postJson($base.'/tugas', [
            'judul' => 'Latihan admin', 'jenis' => 'tugas', 'deadline' => now()->addDay()->toDateTimeString(),
        ])->assertCreated()->assertJsonPath('data.guru_id', $admin->id)->json('data.id');
        $this->patchJson($base.'/tugas/'.$taskId, [
            'judul' => 'Latihan diperbarui', 'jenis' => 'tugas', 'deadline' => now()->addDays(2)->toDateTimeString(),
        ])->assertOk();
        $examId = $this->postJson($base.'/ujian', [
            'judul' => 'Ujian admin', 'jenis_ujian' => 'UH', 'tanggal' => now()->toDateString(),
        ])->assertCreated()->json('data.id');
        $this->getJson($base.'/ujian/'.$examId.'/penilaian')->assertOk()->assertJsonCount(1, 'data.data');
        $this->putJson($base.'/penilaian', ['siswa_id' => $student->id, 'tugas_id' => $taskId, 'nilai' => 80])->assertOk();
        $this->putJson($base.'/penilaian', ['siswa_id' => $student->id, 'ujian_id' => $examId, 'nilai' => 90])->assertOk();
        $this->postJson($base.'/jadwal', [
            'hari' => 'senin', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00',
        ])->assertCreated();
        $recap = $this->postJson($base.'/rekap', [
            'nama_konfigurasi' => 'Rekap admin', 'status' => 'aktif',
            'komponen' => [['jenis' => 'US', 'bobot' => 100, 'metode' => 'manual']],
        ])->assertCreated()->json('data');
        $this->putJson($base.'/rekap/'.$recap['id'].'/komponen/'.$recap['komponen'][0]['id'].'/manual', [
            'siswa_id' => $student->id, 'nilai' => 85,
        ])->assertOk();
        $this->getJson($base.'/rekap')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($base.'/rekap/'.$recap['id'])->assertOk()->assertJsonPath('data.data.0.nilai_akhir', 85);

        $this->assertDatabaseHas('kelas_mapel', [
            'id' => $room->id, 'guru_pembuat_id' => $teacher->id, 'nama_kelas_mapel' => 'Kelas ditinjau admin',
        ]);
        $this->assertDatabaseHas('anggota_kelas', ['id' => $member->id, 'status' => 'diterima', 'approved_by' => $admin->id]);
        $this->assertDatabaseHas('tugas', ['id' => $taskId, 'judul' => 'Latihan diperbarui', 'guru_id' => $admin->id]);
        $this->assertDatabaseHas('penilaian', ['tugas_id' => $taskId, 'siswa_id' => $student->id, 'nilai' => 80, 'dinilai_oleh' => $admin->id]);
        $this->assertDatabaseHas('penilaian', ['ujian_id' => $examId, 'siswa_id' => $student->id, 'nilai' => 90, 'dinilai_oleh' => $admin->id]);
        $this->assertDatabaseHas('nilai_rekap_manual', [
            'komponen_rekap_id' => $recap['komponen'][0]['id'], 'siswa_id' => $student->id, 'nilai' => 85, 'dinilai_oleh' => $admin->id,
        ]);
        $this->assertDatabaseMissing('whitelist_guru_kelas', ['kelas_mapel_id' => $room->id, 'guru_id' => $admin->id]);
    }

    public function test_founder_manages_multiple_owned_schools_but_cannot_manage_a_foreign_school(): void
    {
        $first = Sekolah::factory()->withAdmin()->create();
        $second = Sekolah::factory()->withAdmin()->create(['admin_guru_id' => $first->admin_guru_id]);
        $foreign = Sekolah::factory()->withAdmin()->create();
        $rooms = collect([$first, $second, $foreign])->map(fn (Sekolah $school): KelasMapel => KelasMapel::factory()->create([
            'sekolah_id' => $school->id, 'guru_pembuat_id' => Guru::factory()->inSchool($school),
        ]));
        $admin = $first->admin;
        $rooms[2]->whitelist()->create([
            'guru_id' => $admin->id, 'ditambahkan_oleh' => $rooms[2]->guru_pembuat_id, 'status' => 'aktif',
        ]);

        $this->actingAs($admin->user)->withSession(['active_school_id' => $first->id])
            ->getJson('/api/v1/kelas-mapel')->assertJsonCount(2, 'data.data');
        foreach ([$rooms[0], $rooms[1]] as $room) {
            $this->getJson('/api/v1/kelas-mapel/'.$room->id)->assertOk();
            $this->patchJson('/api/v1/kelas-mapel/'.$room->id, ['deskripsi' => 'Dikelola pendiri'])->assertOk();
            $this->assertDatabaseHas('kelas_mapel', ['id' => $room->id, 'deskripsi' => 'Dikelola pendiri']);
        }
        $this->getJson('/api/v1/kelas-mapel/'.$rooms[2]->id)->assertForbidden();
        $this->patchJson('/api/v1/kelas-mapel/'.$rooms[2]->id, ['deskripsi' => 'Tidak diizinkan'])->assertForbidden();
        $this->postJson('/api/v1/kelas-mapel/'.$rooms[2]->id.'/tugas', [])->assertForbidden();
        $this->assertDatabaseMissing('kelas_mapel', ['id' => $rooms[2]->id, 'deskripsi' => 'Tidak diizinkan']);
    }

    public function test_selected_school_creation_requires_its_own_subscription_and_allows_a_piket_founder(): void
    {
        $this->freezeTime();
        $first = Sekolah::factory()->withAdmin()->create();
        $admin = $first->admin;
        $second = Sekolah::factory()->withAdmin()->create(['admin_guru_id' => $admin->id]);
        $admin->update(['jenis_guru' => 'guru_piket']);
        $admin->keanggotaanSekolah()->update(['jenis_guru' => 'guru_piket']);
        $second->update(['subscription_ends_at' => now()->subSecond()]);

        $this->actingAs($admin->user->fresh())->withSession(['active_school_id' => $first->id])
            ->postJson('/api/v1/kelas-mapel', ['nama_kelas_mapel' => 'Kelas pertama'])
            ->assertCreated()->assertJsonPath('data.sekolah_id', $first->id);
        $this->postJson('/api/v1/kelas-mapel', ['nama_kelas_mapel' => 'Kelas kedua', 'sekolah_id' => $second->id])
            ->assertUnprocessable()->assertJsonValidationErrors('sekolah');
        $this->assertDatabaseCount('kelas_mapel', 1);
        $second->update(['subscription_ends_at' => now()->addDay()]);
        $this->postJson('/api/v1/kelas-mapel', ['nama_kelas_mapel' => 'Kelas kedua', 'sekolah_id' => $second->id])
            ->assertCreated()->assertJsonPath('data.sekolah_id', $second->id)->assertJsonPath('data.guru_pembuat_id', $admin->id);
        $this->assertDatabaseHas('kelas_mapel', ['nama_kelas_mapel' => 'Kelas pertama', 'sekolah_id' => $first->id]);
        $this->assertDatabaseHas('kelas_mapel', ['nama_kelas_mapel' => 'Kelas kedua', 'sekolah_id' => $second->id]);
    }

    public function test_creator_and_implicit_founder_access_cannot_be_removed_through_class_whitelist(): void
    {
        $school = Sekolah::factory()->withAdmin()->create();
        $teacher = Guru::factory()->inSchool($school)->create();
        $colleague = Guru::factory()->inSchool($school)->create();
        $room = KelasMapel::factory()->create(['guru_pembuat_id' => $teacher->id, 'sekolah_id' => $school->id]);
        $base = '/api/v1/kelas-mapel/'.$room->id;

        $this->actingAs($school->admin->user)->getJson($base.'/whitelist')
            ->assertJsonPath('implicit_admin.id', $school->admin_guru_id);
        $this->postJson($base.'/whitelist', ['guru_id' => $teacher->id, 'status' => 'nonaktif'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson($base.'/whitelist', ['guru_id' => $colleague->id, 'status' => 'aktif'])->assertOk();
        $this->actingAs($teacher->user)->postJson($base.'/whitelist', [
            'guru_id' => $school->admin_guru_id, 'status' => 'nonaktif',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('whitelist_guru_kelas', ['kelas_mapel_id' => $room->id, 'guru_id' => $teacher->id, 'status' => 'aktif']);
        $this->assertDatabaseHas('whitelist_guru_kelas', ['kelas_mapel_id' => $room->id, 'guru_id' => $colleague->id, 'status' => 'aktif']);
        $this->assertTrue($school->admin->user->can('manageAcademic', $room));
    }

    public function test_class_scoped_teacher_directory_uses_resource_school_instead_of_selected_school(): void
    {
        $first = Sekolah::factory()->withAdmin()->create();
        $second = Sekolah::factory()->withAdmin()->create(['admin_guru_id' => $first->admin_guru_id]);
        $teacher = Guru::factory()->inSchool($second)->create();
        $teacher->keanggotaanSekolah()->where('sekolah_id', $second->id)->update(['jenis_guru' => 'guru_piket']);
        $room = KelasMapel::factory()->create(['guru_pembuat_id' => $teacher->id, 'sekolah_id' => $second->id]);
        $foreign = KelasMapel::factory()->create(['sekolah_id' => Sekolah::factory()->create()->id]);

        $response = $this->actingAs($first->admin->user)->withSession(['active_school_id' => $first->id])
            ->getJson('/api/v1/master-data?kelas_mapel_id='.$room->id)->assertOk()->assertJsonCount(2, 'guru');
        $this->assertSame('guru_piket', collect($response->json('guru'))->firstWhere('id', $teacher->id)['jenis_guru']);
        $this->getJson('/api/v1/master-data?kelas_mapel_id='.$foreign->id)->assertForbidden();
        $this->actingAs(Siswa::factory()->create()->user)->getJson('/api/v1/master-data?kelas_mapel_id='.$room->id)->assertForbidden();
    }

    public function test_dashboard_includes_student_requests_in_all_founder_managed_classes(): void
    {
        $school = Sekolah::factory()->withAdmin()->create();
        $teacher = Guru::factory()->inSchool($school)->create();
        $room = KelasMapel::factory()->create(['guru_pembuat_id' => $teacher->id, 'sekolah_id' => $school->id]);
        $room->anggota()->create([
            'siswa_id' => Siswa::factory()->create()->id, 'status' => 'pending', 'join_method' => 'kode', 'requested_at' => now(),
        ]);
        $foreign = KelasMapel::factory()->create();
        $foreign->anggota()->create([
            'siswa_id' => Siswa::factory()->create()->id, 'status' => 'pending', 'join_method' => 'kode', 'requested_at' => now(),
        ]);

        $this->actingAs($school->admin->user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->component('TeacherDashboard')
                ->where('stats.pending', 1)->has('requests', 1)->where('requests.0.kelas_mapel_id', $room->id));
    }

    public function test_school_admin_can_reopen_an_archived_class_but_cannot_write_academics_while_archived(): void
    {
        $school = Sekolah::factory()->withAdmin()->create();
        $room = KelasMapel::factory()->create([
            'guru_pembuat_id' => Guru::factory()->inSchool($school), 'sekolah_id' => $school->id, 'status' => 'arsip',
        ]);
        $user = $school->admin->user;

        $this->assertTrue($user->can('update', $room));
        $this->assertTrue($user->can('viewRekap', $room));
        $this->assertFalse($user->can('manageAcademic', $room));
        $this->assertFalse($user->can('reviewMembers', $room));
        $this->actingAs($user)->postJson('/api/v1/kelas-mapel/'.$room->id.'/tugas', [])->assertForbidden();
        $this->patchJson('/api/v1/kelas-mapel/'.$room->id, ['status' => 'aktif'])->assertOk();
        $this->assertDatabaseHas('kelas_mapel', ['id' => $room->id, 'status' => 'aktif']);
    }
}
