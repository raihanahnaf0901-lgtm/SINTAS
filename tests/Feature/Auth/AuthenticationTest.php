<?php

namespace Tests\Feature\Auth;

use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_teacher_uses_account_password_and_cannot_login_by_identity_only(): void
    {
        $guru = Guru::factory()->create();
        $this->post('/login', ['role' => 'guru', 'nama' => $guru->nama_lengkap, 'nip' => $guru->nip])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['role' => 'guru', 'email' => $guru->user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($guru->user);
    }

    public function test_student_cannot_bypass_otp(): void
    {
        $siswa = Siswa::factory()->create();
        $this->post('/login', ['role' => 'siswa', 'email' => $siswa->user->email, 'password' => 'password'])
            ->assertSessionHasErrors('role');
        $this->assertGuest();
    }

    public function test_inactive_teacher_and_wrong_password_are_rejected(): void
    {
        $guru = Guru::factory()->create();
        $this->post('/login', ['role' => 'guru', 'email' => $guru->user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $guru->user->update(['status' => 'nonaktif']);
        $this->post('/login', ['role' => 'guru', 'email' => $guru->user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_ends_session(): void
    {
        $this->actingAs(Siswa::factory()->create()->user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
