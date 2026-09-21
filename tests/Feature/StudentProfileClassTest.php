<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StudentProfileClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_save_a_typed_class_without_master_classes(): void
    {
        $siswa = Siswa::factory()->create();

        $this->actingAs($siswa->user)->patchJson('/api/v1/profil/siswa', [
            'nama_lengkap' => $siswa->nama_lengkap, 'nis' => $siswa->nis, 'kelas_siswa' => '  X PPLG 1  ',
        ])->assertOk()->assertJsonPath('data.siswa.kelas_siswa', 'X PPLG 1');

        $this->assertDatabaseHas('siswa', ['id' => $siswa->id, 'kelas_siswa' => 'X PPLG 1', 'kelas_id' => null]);
        $this->assertDatabaseCount('kelas', 0);
        $this->assertDatabaseCount('anggota_kelas', 0);
    }

    public function test_editing_a_students_class_does_not_rename_shared_classes_or_other_profiles(): void
    {
        $kelas = Kelas::factory()->create(['nama_kelas' => 'X PPLG 1']);
        $siswa = Siswa::factory()->for($kelas)->create(['kelas_siswa' => 'X PPLG 1']);
        $other = Siswa::factory()->for($kelas)->create(['kelas_siswa' => 'X PPLG 1']);

        $this->actingAs($siswa->user)->patchJson('/api/v1/profil/siswa', [
            'nama_lengkap' => $siswa->nama_lengkap, 'nis' => $siswa->nis, 'kelas_siswa' => 'XI PPLG 2',
        ])->assertOk()->assertJsonPath('data.siswa.kelas_siswa', 'XI PPLG 2');

        $this->assertDatabaseHas('siswa', ['id' => $siswa->id, 'kelas_siswa' => 'XI PPLG 2', 'kelas_id' => $kelas->id]);
        $this->assertDatabaseHas('siswa', ['id' => $other->id, 'kelas_siswa' => 'X PPLG 1']);
        $this->assertDatabaseHas('kelas', ['id' => $kelas->id, 'nama_kelas' => 'X PPLG 1']);
        $this->assertDatabaseCount('kelas', 1);
        $this->assertDatabaseCount('anggota_kelas', 0);
    }

    public function test_student_can_clear_the_optional_class_name(): void
    {
        $kelas = Kelas::factory()->create(['nama_kelas' => 'X PPLG 1']);
        $siswa = Siswa::factory()->for($kelas)->create(['kelas_siswa' => 'X PPLG 1']);

        $this->actingAs($siswa->user)->patchJson('/api/v1/profil/siswa', [
            'nama_lengkap' => $siswa->nama_lengkap, 'nis' => $siswa->nis, 'kelas_siswa' => '   ',
        ])->assertOk()->assertJsonPath('data.siswa.kelas_siswa', null);

        $this->assertDatabaseHas('siswa', ['id' => $siswa->id, 'kelas_siswa' => null, 'kelas_id' => $kelas->id]);
    }

    public function test_omitting_the_class_field_preserves_the_saved_name(): void
    {
        $siswa = Siswa::factory()->create(['kelas_siswa' => 'X PPLG 1']);

        $this->actingAs($siswa->user)->patchJson('/api/v1/profil/siswa', [
            'nama_lengkap' => $siswa->nama_lengkap, 'nis' => $siswa->nis,
        ])->assertOk();

        $this->assertDatabaseHas('siswa', ['id' => $siswa->id, 'kelas_siswa' => 'X PPLG 1']);
    }

    #[TestWith(['too_long', 'Kelas siswa maksimal 255 karakter.'])]
    #[TestWith(['array', 'Kelas siswa harus berupa teks.'])]
    public function test_invalid_class_names_return_validation_errors_without_changing_the_profile(string $case, string $message): void
    {
        $siswa = Siswa::factory()->create(['kelas_siswa' => 'X PPLG 1']);

        $this->actingAs($siswa->user)->patchJson('/api/v1/profil/siswa', [
            'nama_lengkap' => $siswa->nama_lengkap, 'nis' => $siswa->nis,
            'kelas_siswa' => $case === 'too_long' ? str_repeat('A', 256) : ['X PPLG 2'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['kelas_siswa'])
            ->assertJsonPath('errors.kelas_siswa.0', $message);

        $this->assertDatabaseHas('siswa', ['id' => $siswa->id, 'kelas_siswa' => 'X PPLG 1']);
    }

    public function test_profile_page_receives_the_saved_class_name(): void
    {
        $siswa = Siswa::factory()->create(['kelas_siswa' => 'XI PPLG 2']);

        $this->actingAs($siswa->user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')->where('siswa.kelas_siswa', 'XI PPLG 2')
            ->where('auth.user.siswa.kelas_siswa', 'XI PPLG 2'));
    }

    public function test_guest_cannot_save_a_students_class(): void
    {
        $siswa = Siswa::factory()->create(['kelas_siswa' => 'X PPLG 1']);

        $this->patchJson('/api/v1/profil/siswa', [
            'nama_lengkap' => $siswa->nama_lengkap, 'nis' => $siswa->nis, 'kelas_siswa' => 'XI PPLG 2',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('siswa', ['id' => $siswa->id, 'kelas_siswa' => 'X PPLG 1']);
    }

    public function test_teacher_cannot_use_the_student_profile_endpoint(): void
    {
        $guru = Guru::factory()->create();
        $siswa = Siswa::factory()->create(['kelas_siswa' => 'X PPLG 1']);

        $this->actingAs($guru->user)->patchJson('/api/v1/profil/siswa', [
            'nama_lengkap' => $siswa->nama_lengkap, 'nis' => $siswa->nis,
            'siswa_id' => $siswa->id, 'kelas_siswa' => 'XI PPLG 2',
        ])->assertForbidden();

        $this->assertDatabaseHas('siswa', ['id' => $siswa->id, 'kelas_siswa' => 'X PPLG 1']);
    }

    public function test_migration_preserves_existing_class_names_including_inactive_classes(): void
    {
        $migration = require database_path('migrations/2026_09_21_034841_add_kelas_siswa_to_siswa_table.php');
        $migration->down();
        $kelas = Kelas::factory()->create(['nama_kelas' => 'X PPLG 1', 'status' => 'nonaktif']);
        $siswa = Siswa::factory()->for($kelas)->create();
        $unassigned = Siswa::factory()->create();

        $migration->up();

        $this->assertDatabaseHas('siswa', ['id' => $siswa->id, 'kelas_siswa' => 'X PPLG 1', 'kelas_id' => $kelas->id]);
        $this->assertDatabaseHas('siswa', ['id' => $unassigned->id, 'kelas_siswa' => null, 'kelas_id' => null]);
    }
}
