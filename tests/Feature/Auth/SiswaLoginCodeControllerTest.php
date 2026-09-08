<?php

namespace Tests\Feature\Auth;

use App\Models\Siswa;
use App\Models\SiswaLoginCode;
use App\Models\User;
use App\Notifications\KodeVerifikasiLoginSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SiswaLoginCodeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_belajar_id_credentials_send_a_hashed_login_code(): void
    {
        $user = $this->createStudent();
        $this->freezeTime();
        Notification::fake();

        $response = $this->postJson(route('siswa-login-code.store'), [
            'email' => ' SISWA@BELAJAR.ID ',
            'password' => 'password',
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('message', 'Kode verifikasi telah dikirim ke email belajar.id Anda.')
            ->assertJsonPath('expires_in_minutes', 10);
        $this->assertGuest();

        $loginCode = SiswaLoginCode::query()->sole();
        $this->assertSame($user->id, $loginCode->user_id);
        $this->assertSame(
            now()->addMinutes(10)->format('Y-m-d H:i:s'),
            $loginCode->expires_at->format('Y-m-d H:i:s'),
        );
        $this->assertNull($loginCode->used_at);

        Notification::assertSentTo(
            $user,
            KodeVerifikasiLoginSiswa::class,
            function (KodeVerifikasiLoginSiswa $notification) use ($loginCode): bool {
                return preg_match('/^\d{6}$/', $notification->code) === 1
                    && Hash::check($notification->code, $loginCode->code_hash)
                    && $notification->code !== $loginCode->code_hash;
            },
        );
    }

    public function test_wrong_password_does_not_send_or_store_a_code(): void
    {
        $this->createStudent();
        Notification::fake();

        $response = $this->postJson(route('siswa-login-code.store'), [
            'email' => 'siswa@belajar.id',
            'password' => 'password-salah',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email atau password siswa tidak cocok.');
        $this->assertDatabaseCount('siswa_login_codes', 0);
        Notification::assertNothingSent();
    }

    public function test_non_belajar_id_email_is_rejected(): void
    {
        Notification::fake();

        $response = $this->postJson(route('siswa-login-code.store'), [
            'email' => 'siswa@gmail.com',
            'password' => 'password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Siswa wajib menggunakan email dengan domain @belajar.id.');
        $this->assertDatabaseCount('siswa_login_codes', 0);
        Notification::assertNothingSent();
    }

    public function test_non_student_account_cannot_request_a_student_login_code(): void
    {
        User::factory()->create([
            'email' => 'guru@belajar.id',
            'role' => 'guru',
        ]);
        Notification::fake();

        $response = $this->postJson(route('siswa-login-code.store'), [
            'email' => 'guru@belajar.id',
            'password' => 'password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email atau password siswa tidak cocok.');
        $this->assertDatabaseCount('siswa_login_codes', 0);
        Notification::assertNothingSent();
    }

    public function test_requesting_a_new_code_invalidates_the_previous_code(): void
    {
        $user = $this->createStudent();
        Notification::fake();

        $this->postJson(route('siswa-login-code.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(202);

        $this->postJson(route('siswa-login-code.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(202);

        $codes = SiswaLoginCode::query()->oldest('id')->get();
        $this->assertCount(2, $codes);
        $this->assertNotNull($codes[0]->used_at);
        $this->assertNull($codes[1]->used_at);
        Notification::assertSentTimes(KodeVerifikasiLoginSiswa::class, 2);
    }

    public function test_code_requests_are_rate_limited(): void
    {
        $user = $this->createStudent('dibatasi@belajar.id');
        Notification::fake();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson(route('siswa-login-code.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertStatus(202);
        }

        $this->postJson(route('siswa-login-code.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('siswa_login_codes', 3);
        Notification::assertSentTimes(KodeVerifikasiLoginSiswa::class, 3);
    }

    private function createStudent(string $email = 'siswa@belajar.id'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'role' => 'siswa',
        ]);
        Siswa::factory()->for($user)->create();

        return $user;
    }
}
