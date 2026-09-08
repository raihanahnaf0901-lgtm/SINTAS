<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\KeanggotaanRuangMapel;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\RuangMapel;
use App\Models\Siswa;
use App\StatusKeanggotaanRuangMapel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuangMapelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_can_create_room_for_a_subject_and_class_they_teach(): void
    {
        $guru = Guru::factory()->create();
        $mapel = Mapel::factory()->create(['nama_mapel' => 'Matematika']);
        $kelas = Kelas::factory()->create(['nama_kelas' => '10 IPA 1']);
        Jadwal::factory()->for($guru)->for($mapel)->for($kelas)->create();

        $response = $this->actingAs($guru->user)->postJson(route('ruang-mapels.store'), [
            'mapel_id' => $mapel->id,
            'kelas_id' => $kelas->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Ruang mata pelajaran berhasil dibuat.')
            ->assertJsonPath('data.nama_ruang', 'Matematika - 10 IPA 1')
            ->assertJsonPath('data.mapel.id', $mapel->id)
            ->assertJsonPath('data.kelas.id', $kelas->id)
            ->assertJsonPath('data.aktif', true);

        $this->assertDatabaseHas('ruang_mapels', [
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'kelas_id' => $kelas->id,
            'nama_ruang' => 'Matematika - 10 IPA 1',
        ]);
    }

    public function test_guru_cannot_create_room_without_a_matching_teaching_schedule(): void
    {
        $guru = Guru::factory()->create();
        $mapel = Mapel::factory()->create();
        $kelas = Kelas::factory()->create();

        $response = $this->actingAs($guru->user)->postJson(route('ruang-mapels.store'), [
            'mapel_id' => $mapel->id,
            'kelas_id' => $kelas->id,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mapel_id'])
            ->assertJsonPath('errors.mapel_id.0', 'Anda tidak memiliki jadwal mata pelajaran ini untuk kelas yang dipilih.');

        $this->assertDatabaseCount('ruang_mapels', 0);
    }

    public function test_guru_cannot_create_the_same_subject_room_twice(): void
    {
        $guru = Guru::factory()->create();
        $mapel = Mapel::factory()->create();
        $kelas = Kelas::factory()->create();
        Jadwal::factory()->for($guru)->for($mapel)->for($kelas)->create();
        RuangMapel::factory()->for($guru)->for($mapel)->for($kelas)->create();

        $response = $this->actingAs($guru->user)->postJson(route('ruang-mapels.store'), [
            'mapel_id' => $mapel->id,
            'kelas_id' => $kelas->id,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.mapel_id.0', 'Ruang mata pelajaran untuk jadwal ini sudah dibuat.');

        $this->assertDatabaseCount('ruang_mapels', 1);
    }

    public function test_non_guru_cannot_create_a_subject_room(): void
    {
        $siswa = Siswa::factory()->create();
        $mapel = Mapel::factory()->create();
        $kelas = Kelas::factory()->create();

        $response = $this->actingAs($siswa->user)->postJson(route('ruang-mapels.store'), [
            'mapel_id' => $mapel->id,
            'kelas_id' => $kelas->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('ruang_mapels', 0);
    }

    public function test_guru_room_list_contains_pending_student_requests(): void
    {
        $guru = Guru::factory()->create();
        $kelas = Kelas::factory()->create();
        $ruangMapel = RuangMapel::factory()
            ->for($guru)
            ->for($kelas)
            ->create(['nama_ruang' => 'Fisika 10']);
        Jadwal::factory()
            ->for($guru)
            ->for($ruangMapel->mapel)
            ->for($kelas)
            ->create();
        $siswa = Siswa::factory()->for($kelas)->create([
            'nama_lengkap' => 'Alya Putri',
            'nis' => '10001',
        ]);
        $keanggotaan = KeanggotaanRuangMapel::factory()
            ->for($ruangMapel)
            ->for($siswa)
            ->create();

        $response = $this->actingAs($guru->user)->getJson(route('ruang-mapels.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_ruang', 'Fisika 10')
            ->assertJsonPath('data.0.jumlah_menunggu', 1)
            ->assertJsonPath('data.0.permintaan_menunggu.0.id', $keanggotaan->id)
            ->assertJsonPath('data.0.permintaan_menunggu.0.siswa.nama', 'Alya Putri')
            ->assertJsonPath('data.0.permintaan_menunggu.0.siswa.nis', '10001')
            ->assertJsonCount(1, 'jadwal_tersedia')
            ->assertJsonPath('jadwal_tersedia.0.mapel.id', $ruangMapel->mapel_id)
            ->assertJsonPath('jadwal_tersedia.0.kelas.id', $kelas->id);
    }

    public function test_student_room_list_contains_membership_status(): void
    {
        $kelas = Kelas::factory()->create();
        $siswa = Siswa::factory()->for($kelas)->create();
        $ruangMapel = RuangMapel::factory()->for($kelas)->create();
        KeanggotaanRuangMapel::factory()
            ->for($ruangMapel)
            ->for($siswa)
            ->create(['status' => StatusKeanggotaanRuangMapel::Diterima]);

        $response = $this->actingAs($siswa->user)->getJson(route('ruang-mapels.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'diterima')
            ->assertJsonPath('data.0.ruang.id', $ruangMapel->id);
    }

    public function test_invitation_link_is_visible_only_to_the_room_class_and_owner(): void
    {
        $kelas = Kelas::factory()->create();
        $kelasLain = Kelas::factory()->create();
        $guru = Guru::factory()->create();
        $ruangMapel = RuangMapel::factory()->for($guru)->for($kelas)->create();
        $siswaSekelas = Siswa::factory()->for($kelas)->create();
        $siswaKelasLain = Siswa::factory()->for($kelasLain)->create();

        $this->actingAs($guru->user)
            ->getJson(route('ruang-mapels.show', $ruangMapel))
            ->assertOk()
            ->assertJsonPath('data.kode', $ruangMapel->kode);

        $this->actingAs($siswaSekelas->user)
            ->getJson(route('ruang-mapels.show', $ruangMapel))
            ->assertOk()
            ->assertJsonPath('data.status_keanggotaan', null);

        $this->actingAs($siswaKelasLain->user)
            ->getJson(route('ruang-mapels.show', $ruangMapel))
            ->assertForbidden();
    }

    public function test_guest_receives_401_for_subject_room_data(): void
    {
        $this->getJson(route('ruang-mapels.index'))->assertUnauthorized();
    }
}
