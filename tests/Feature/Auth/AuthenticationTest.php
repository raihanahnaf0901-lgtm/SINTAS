<?php

namespace Tests\Feature\Auth;

use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
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

    #[TestWith(['siswa'])]
    #[TestWith(['guru'])]
    public function test_active_accounts_login_with_password_without_sending_otp(string $role): void
    {
        Notification::fake();
        $account = ($role === 'guru' ? Guru::factory() : Siswa::factory())->create()->user;

        $this->post('/login', ['role' => $role, 'email' => strtoupper($account->email), 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($account);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('otp_verifications', 0);
    }

    #[TestWith(['pending'])]
    #[TestWith(['nonaktif'])]
    public function test_student_must_finish_registration_and_be_active_before_password_login(string $status): void
    {
        $account = Siswa::factory()->create()->user;
        $account->update(['status' => $status, 'email_verified_at' => null]);

        $this->post('/login', ['role' => 'siswa', 'email' => $account->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_student_cannot_login_using_teacher_role(): void
    {
        $account = Siswa::factory()->create()->user;

        $this->post('/login', ['role' => 'guru', 'email' => $account->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_wrong_student_password_is_limited_and_success_clears_attempts(): void
    {
        $account = Siswa::factory()->create()->user;
        $key = 'account-login:'.$account->email.'|127.0.0.1';
        $credentials = ['role' => 'siswa', 'email' => $account->email, 'password' => 'password'];

        $this->post('/login', [...$credentials, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, RateLimiter::attempts($key));
        $this->post('/login', $credentials)->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($account);
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_password_login_is_blocked_after_five_failed_attempts(): void
    {
        $this->freezeTime();
        $account = Siswa::factory()->create()->user;
        $credentials = ['role' => 'siswa', 'email' => $account->email, 'password' => 'wrong-password'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', $credentials)->assertSessionHasErrors('email');
        }
        $this->post('/login', [...$credentials, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan login. Tunggu 60 detik.']);

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
