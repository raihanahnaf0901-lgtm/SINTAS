<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Guru;
use App\Models\KelasMapel;
use App\Models\Mapel;
use App\Models\Siswa;
use App\Models\Tugas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KelasMapelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_creates_class_without_preexisting_schedule_and_can_edit_code(): void
    {
        $guru = Guru::factory()->create();
        $mapel = Mapel::factory()->create();
        $id = $this->actingAs($guru->user)->postJson('/api/v1/kelas-mapel', [
            'mapel_id' => $mapel->id, 'nama_kelas_mapel' => 'Matematika X', 'kode_kelas' => 'mtk-123456',
            'guru_pembuat_id' => 999,
        ])->assertCreated()->assertJsonPath('data.kode_kelas', 'MTK-123456')
            ->assertJsonPath('data.guru_pembuat_id', $guru->id)->json('data.id');
        $kelas = KelasMapel::findOrFail($id);
        $oldToken = $kelas->invite_token;
        $this->patchJson('/api/v1/kelas-mapel/'.$id, ['kode_kelas' => 'MTK-BARU', 'regenerate_invite' => true])
            ->assertOk()->assertJsonPath('data.kode_kelas', 'MTK-BARU');
        $this->assertNotSame($oldToken, $kelas->fresh()->invite_token);
        $this->getJson('/kelas-mapel/undangan/'.$oldToken)->assertNotFound();
    }

    public function test_student_is_locked_out_until_owner_approves(): void
    {
        $kelas = KelasMapel::factory()->create();
        $student = Siswa::factory()->create();
        Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $this->actingAs($student->user);
        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.summaries.0.total', 0)->assertJsonCount(0, 'data.subjects');
        $id = $this->postJson('/api/v1/kelas-mapel/gabung', [
            'mapel_id' => $kelas->mapel_id, 'kode_kelas' => strtolower($kelas->kode_kelas),
            'status' => 'diterima', 'approved_by' => $kelas->guru_pembuat_id,
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->getJson('/api/v1/kelas-mapel/'.$kelas->id)->assertForbidden();
        $this->getJson('/api/v1/kelas-mapel/'.$kelas->id.'/tugas')->assertForbidden();
        $this->actingAs($kelas->pembuat->user)->patchJson('/api/v1/kelas-mapel/'.$kelas->id.'/anggota/'.$id, ['status' => 'diterima'])->assertOk();
        $this->actingAs($student->user)->getJson('/api/v1/kelas-mapel/'.$kelas->id)->assertOk()
            ->assertJsonMissingPath('data.invite_token')->assertJsonMissingPath('data.kode_kelas');
        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.summaries.0.total', 1)->assertJsonCount(1, 'data.subjects');
    }

    public function test_link_join_also_waits_and_requires_matching_subject(): void
    {
        $kelas = KelasMapel::factory()->create();
        $student = Siswa::factory()->create();
        $this->actingAs($student->user)->postJson('/api/v1/kelas-mapel/gabung', [
            'mapel_id' => Mapel::factory()->create()->id, 'invite_token' => $kelas->invite_token,
        ])->assertUnprocessable();
        $this->postJson('/api/v1/kelas-mapel/gabung', [
            'mapel_id' => $kelas->mapel_id, 'invite_token' => $kelas->invite_token,
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.join_method', 'link');
        $this->getJson('/api/v1/kelas-mapel/'.$kelas->id.'/tugas')->assertForbidden();
    }

    public function test_duplicate_join_is_rejected_but_rejected_student_can_request_again(): void
    {
        $kelas = KelasMapel::factory()->create();
        $student = Siswa::factory()->create();
        $data = ['mapel_id' => $kelas->mapel_id, 'kode_kelas' => $kelas->kode_kelas];
        $this->actingAs($student->user)->postJson('/api/v1/kelas-mapel/gabung', $data)->assertCreated();
        $this->postJson('/api/v1/kelas-mapel/gabung', $data)->assertUnprocessable();
        $member = AnggotaKelas::sole();
        $this->actingAs($kelas->pembuat->user)->patchJson('/api/v1/kelas-mapel/'.$kelas->id.'/anggota/'.$member->id, ['status' => 'ditolak'])->assertOk();
        $this->actingAs($student->user)->postJson('/api/v1/kelas-mapel/gabung', $data)->assertCreated();
        $this->assertDatabaseCount('anggota_kelas', 1);
        $this->assertNull($member->fresh()->approved_by);
    }

    public function test_only_owner_can_manage_whitelist_and_review_members(): void
    {
        $kelas = KelasMapel::factory()->create();
        $other = Guru::factory()->create();
        $member = $kelas->anggota()->create(['siswa_id' => Siswa::factory()->create()->id, 'join_method' => 'kode', 'requested_at' => now()]);
        $kelas->whitelist()->create(['guru_id' => $other->id, 'ditambahkan_oleh' => $kelas->guru_pembuat_id, 'status' => 'aktif']);
        $this->actingAs($other->user)->getJson('/api/v1/kelas-mapel/'.$kelas->id)->assertOk();
        $this->patchJson('/api/v1/kelas-mapel/'.$kelas->id.'/anggota/'.$member->id, ['status' => 'diterima'])->assertForbidden();
        $this->postJson('/api/v1/kelas-mapel/'.$kelas->id.'/whitelist', ['guru_id' => $other->id, 'status' => 'nonaktif'])->assertForbidden();
        $this->patchJson('/api/v1/kelas-mapel/'.$kelas->id, ['kode_kelas' => 'HACK-123'])->assertForbidden();
    }

    public function test_piket_can_monitor_but_cannot_make_academic_changes(): void
    {
        $kelas = KelasMapel::factory()->create();
        $piket = Guru::factory()->create(['jenis_guru' => 'guru_piket']);
        $base = '/api/v1/kelas-mapel/'.$kelas->id;
        $this->actingAs($piket->user)->getJson($base)->assertForbidden();
        $this->actingAs($kelas->pembuat->user)->postJson($base.'/whitelist', ['guru_id' => $piket->id, 'status' => 'aktif'])->assertOk();
        $this->actingAs($piket->user)->getJson($base)->assertOk();
        $this->getJson($base.'/anggota')->assertOk();
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $this->getJson($base.'/tugas/'.$task->id.'/pengumpulan')->assertOk();
        foreach (['tugas', 'ujian', 'jadwal', 'rekap'] as $resource) {
            $this->postJson($base.'/'.$resource, [])->assertForbidden();
        }
        $this->putJson($base.'/penilaian', [])->assertForbidden();
        $this->postJson('/api/v1/kelas-mapel', [])->assertForbidden();
        $this->actingAs($kelas->pembuat->user)->postJson($base.'/whitelist', ['guru_id' => $piket->id, 'status' => 'nonaktif'])->assertOk();
        $this->actingAs($piket->user)->getJson($base)->assertForbidden();
    }

    public function test_owner_cannot_remove_their_own_whitelist_entry(): void
    {
        $kelas = KelasMapel::factory()->create();
        $this->actingAs($kelas->pembuat->user)->postJson('/api/v1/kelas-mapel/'.$kelas->id.'/whitelist', [
            'guru_id' => $kelas->guru_pembuat_id, 'status' => 'nonaktif',
        ])->assertUnprocessable();
    }

    public function test_archived_class_denies_join_and_academic_writes(): void
    {
        $kelas = KelasMapel::factory()->create(['status' => 'arsip']);
        $this->actingAs($kelas->pembuat->user)->postJson('/api/v1/kelas-mapel/'.$kelas->id.'/tugas', [])->assertForbidden();
        $this->actingAs(Siswa::factory()->create()->user)->postJson('/api/v1/kelas-mapel/gabung', [
            'mapel_id' => $kelas->mapel_id, 'kode_kelas' => $kelas->kode_kelas,
        ])->assertUnprocessable();
    }

    public function test_guests_and_inactive_accounts_cannot_use_api(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
        $siswa = Siswa::factory()->create();
        $siswa->user->update(['status' => 'nonaktif']);
        $this->actingAs($siswa->user)->getJson('/api/v1/dashboard')->assertForbidden();
    }
}
