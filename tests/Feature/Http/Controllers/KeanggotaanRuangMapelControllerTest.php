<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Guru;
use App\Models\KeanggotaanRuangMapel;
use App\Models\Kelas;
use App\Models\RuangMapel;
use App\Models\Siswa;
use App\StatusKeanggotaanRuangMapel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeanggotaanRuangMapelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_request_to_join_a_room_using_its_code(): void
    {
        $kelas = Kelas::factory()->create();
        $siswa = Siswa::factory()->for($kelas)->create();
        $ruangMapel = RuangMapel::factory()->for($kelas)->create(['kode' => 'RM-ABC12345']);

        $response = $this->actingAs($siswa->user)->postJson(route('ruang-mapels.join'), [
            'kode' => ' rm-abc12345 ',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Permintaan bergabung berhasil dikirim dan menunggu persetujuan guru.')
            ->assertJsonPath('data.status', 'menunggu')
            ->assertJsonPath('data.ruang_mapel_id', $ruangMapel->id);

        $this->assertDatabaseHas('keanggotaan_ruang_mapels', [
            'ruang_mapel_id' => $ruangMapel->id,
            'siswa_id' => $siswa->id,
            'status' => 'menunggu',
        ]);
    }

    public function test_student_cannot_request_a_room_for_another_class(): void
    {
        $kelas = Kelas::factory()->create();
        $kelasLain = Kelas::factory()->create();
        $siswa = Siswa::factory()->for($kelas)->create();
        $ruangMapel = RuangMapel::factory()->for($kelasLain)->create();

        $response = $this->actingAs($siswa->user)->postJson(route('ruang-mapels.join'), [
            'kode' => $ruangMapel->kode,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.kode.0', 'Ruang mata pelajaran ini bukan untuk kelas Anda.');

        $this->assertDatabaseCount('keanggotaan_ruang_mapels', 0);
    }

    public function test_invalid_room_code_is_rejected(): void
    {
        $siswa = Siswa::factory()->create();

        $response = $this->actingAs($siswa->user)->postJson(route('ruang-mapels.join'), [
            'kode' => 'TIDAK-ADA',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.kode.0', 'Kode ruang tidak ditemukan atau sudah tidak aktif.');

        $this->assertDatabaseCount('keanggotaan_ruang_mapels', 0);
    }

    public function test_pending_request_cannot_be_submitted_twice(): void
    {
        $kelas = Kelas::factory()->create();
        $siswa = Siswa::factory()->for($kelas)->create();
        $ruangMapel = RuangMapel::factory()->for($kelas)->create();
        KeanggotaanRuangMapel::factory()->for($ruangMapel)->for($siswa)->create();

        $response = $this->actingAs($siswa->user)->postJson(route('ruang-mapels.join'), [
            'kode' => $ruangMapel->kode,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.kode.0', 'Permintaan Anda masih menunggu persetujuan guru.');

        $this->assertDatabaseCount('keanggotaan_ruang_mapels', 1);
    }

    public function test_rejected_student_can_submit_the_request_again(): void
    {
        $kelas = Kelas::factory()->create();
        $siswa = Siswa::factory()->for($kelas)->create();
        $ruangMapel = RuangMapel::factory()->for($kelas)->create();
        $keanggotaan = KeanggotaanRuangMapel::factory()
            ->for($ruangMapel)
            ->for($siswa)
            ->create([
                'status' => StatusKeanggotaanRuangMapel::Ditolak,
                'ditinjau_oleh' => $ruangMapel->guru_id,
                'ditinjau_pada' => now(),
            ]);

        $response = $this->actingAs($siswa->user)->postJson(route('ruang-mapels.join'), [
            'kode' => $ruangMapel->kode,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'menunggu');

        $keanggotaan->refresh();
        $this->assertSame(StatusKeanggotaanRuangMapel::Menunggu, $keanggotaan->status);
        $this->assertNull($keanggotaan->ditinjau_oleh);
        $this->assertNull($keanggotaan->ditinjau_pada);
        $this->assertDatabaseCount('keanggotaan_ruang_mapels', 1);
    }

    public function test_room_teacher_can_accept_a_pending_student(): void
    {
        $guru = Guru::factory()->create();
        $kelas = Kelas::factory()->create();
        $ruangMapel = RuangMapel::factory()->for($guru)->for($kelas)->create();
        $siswa = Siswa::factory()->for($kelas)->create();
        $keanggotaan = KeanggotaanRuangMapel::factory()->for($ruangMapel)->for($siswa)->create();

        $response = $this->actingAs($guru->user)->patchJson(
            route('ruang-mapels.memberships.update', $keanggotaan),
            ['status' => 'diterima'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Siswa berhasil diterima ke ruang mata pelajaran.')
            ->assertJsonPath('data.status', 'diterima');

        $keanggotaan->refresh();
        $this->assertSame(StatusKeanggotaanRuangMapel::Diterima, $keanggotaan->status);
        $this->assertSame($guru->id, $keanggotaan->ditinjau_oleh);
        $this->assertNotNull($keanggotaan->ditinjau_pada);
    }

    public function test_room_teacher_can_reject_a_pending_student(): void
    {
        $guru = Guru::factory()->create();
        $ruangMapel = RuangMapel::factory()->for($guru)->create();
        $keanggotaan = KeanggotaanRuangMapel::factory()->for($ruangMapel)->create();

        $response = $this->actingAs($guru->user)->patchJson(
            route('ruang-mapels.memberships.update', $keanggotaan),
            ['status' => 'ditolak'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Permintaan siswa berhasil ditolak.')
            ->assertJsonPath('data.status', 'ditolak');

        $this->assertDatabaseHas('keanggotaan_ruang_mapels', [
            'id' => $keanggotaan->id,
            'status' => 'ditolak',
            'ditinjau_oleh' => $guru->id,
        ]);
    }

    public function test_teacher_cannot_review_another_teachers_request(): void
    {
        $guruPemilik = Guru::factory()->create();
        $guruLain = Guru::factory()->create();
        $ruangMapel = RuangMapel::factory()->for($guruPemilik)->create();
        $keanggotaan = KeanggotaanRuangMapel::factory()->for($ruangMapel)->create();

        $response = $this->actingAs($guruLain->user)->patchJson(
            route('ruang-mapels.memberships.update', $keanggotaan),
            ['status' => 'diterima'],
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('keanggotaan_ruang_mapels', [
            'id' => $keanggotaan->id,
            'status' => 'menunggu',
            'ditinjau_oleh' => null,
        ]);
    }

    public function test_teacher_must_choose_accept_or_reject_when_reviewing(): void
    {
        $guru = Guru::factory()->create();
        $ruangMapel = RuangMapel::factory()->for($guru)->create();
        $keanggotaan = KeanggotaanRuangMapel::factory()->for($ruangMapel)->create();

        $response = $this->actingAs($guru->user)->patchJson(
            route('ruang-mapels.memberships.update', $keanggotaan),
            ['status' => 'menunggu'],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Keputusan hanya boleh diterima atau ditolak.');

        $this->assertDatabaseHas('keanggotaan_ruang_mapels', [
            'id' => $keanggotaan->id,
            'status' => 'menunggu',
            'ditinjau_oleh' => null,
        ]);
    }

    public function test_non_student_cannot_submit_a_join_request(): void
    {
        $guru = Guru::factory()->create();
        $ruangMapel = RuangMapel::factory()->create();

        $this->actingAs($guru->user)
            ->postJson(route('ruang-mapels.join'), ['kode' => $ruangMapel->kode])
            ->assertForbidden();

        $this->assertDatabaseCount('keanggotaan_ruang_mapels', 0);
    }
}
