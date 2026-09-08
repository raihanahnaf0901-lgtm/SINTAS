<?php

namespace Tests\Feature\Policies;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\RuangMapel;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuangMapelPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_can_manage_only_their_own_subject_room(): void
    {
        $guruPemilik = Guru::factory()->create();
        $guruLain = Guru::factory()->create();
        $ruangMapel = RuangMapel::factory()->for($guruPemilik)->create();

        $this->assertTrue($guruPemilik->user->can('viewAny', RuangMapel::class));
        $this->assertTrue($guruPemilik->user->can('create', RuangMapel::class));
        $this->assertTrue($guruPemilik->user->can('view', $ruangMapel));
        $this->assertTrue($guruPemilik->user->can('update', $ruangMapel));
        $this->assertTrue($guruPemilik->user->can('delete', $ruangMapel));
        $this->assertFalse($guruLain->user->can('view', $ruangMapel));
        $this->assertFalse($guruLain->user->can('update', $ruangMapel));
        $this->assertFalse($guruLain->user->can('delete', $ruangMapel));
    }

    public function test_student_can_view_only_rooms_for_their_class(): void
    {
        $kelas = Kelas::factory()->create();
        $kelasLain = Kelas::factory()->create();
        $siswa = Siswa::factory()->for($kelas)->create();
        $ruangSekelas = RuangMapel::factory()->for($kelas)->create();
        $ruangKelasLain = RuangMapel::factory()->for($kelasLain)->create();

        $this->assertTrue($siswa->user->can('viewAny', RuangMapel::class));
        $this->assertTrue($siswa->user->can('view', $ruangSekelas));
        $this->assertFalse($siswa->user->can('view', $ruangKelasLain));
        $this->assertFalse($siswa->user->can('create', RuangMapel::class));
        $this->assertFalse($siswa->user->can('update', $ruangSekelas));
        $this->assertFalse($siswa->user->can('delete', $ruangSekelas));
    }

    public function test_admin_and_users_without_role_profiles_cannot_access_subject_rooms(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $guruTanpaProfil = User::factory()->create(['role' => 'guru']);
        $siswaTanpaProfil = User::factory()->create(['role' => 'siswa']);

        $this->assertFalse($admin->can('viewAny', RuangMapel::class));
        $this->assertFalse($admin->can('create', RuangMapel::class));
        $this->assertFalse($guruTanpaProfil->can('viewAny', RuangMapel::class));
        $this->assertFalse($guruTanpaProfil->can('create', RuangMapel::class));
        $this->assertFalse($siswaTanpaProfil->can('viewAny', RuangMapel::class));
    }
}
