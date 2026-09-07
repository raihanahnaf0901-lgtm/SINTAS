<?php

namespace Tests\Feature\Auth;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login', [
            'X-Inertia' => 'true',
        ]);

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'role' => 'admin',
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_siswa_can_authenticate_using_their_schema_identity(): void
    {
        $user = User::factory()->create(['role' => 'siswa']);
        $kelas = Kelas::query()->create(['nama_kelas' => '10 Akuntansi 1', 'tingkat' => '10']);
        Siswa::query()->create([
            'user_id' => $user->id,
            'kelas_id' => $kelas->id,
            'nama_lengkap' => 'Raihan Ahnaf',
            'nis' => '12345',
        ]);

        $response = $this->post('/login', [
            'role' => 'siswa',
            'nama_lengkap' => 'Raihan Ahnaf',
            'nis' => '12345',
            'kelas_id' => $kelas->id,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_guru_can_authenticate_using_their_schema_identity(): void
    {
        $user = User::factory()->create(['role' => 'guru']);
        Guru::query()->create([
            'user_id' => $user->id,
            'nama' => 'Budi Santoso',
            'gelar' => 'S.Pd',
            'nip' => '19850101',
        ]);

        $response = $this->post('/login', [
            'role' => 'guru',
            'nama' => 'Budi Santoso',
            'gelar' => 'S.Pd',
            'nip' => '19850101',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'role' => 'admin',
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
