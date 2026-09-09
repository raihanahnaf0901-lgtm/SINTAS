<?php

namespace Tests\Feature\Auth;

use App\Models\SiswaRegistrationCode;
use App\Models\User;
use App\Notifications\KodeVerifikasiRegistrasiSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SiswaRegistrationCodeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_belajar_id_email_sends_a_hashed_registration_code(): void
    {
        $this->freezeTime();
        Notification::fake();

        $response = $this->postJson(route('siswa-registration-code.store'), [
            'name' => '  Budi   Santoso  ',
            'email' => ' SISWA@BELAJAR.ID ',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('message', 'Kode verifikasi pendaftaran telah dikirim ke email belajar.id Anda.')
            ->assertJsonPath('expires_in_minutes', 10);
        $this->assertGuest();

        $registrationCode = SiswaRegistrationCode::query()->sole();
        $this->assertSame('siswa@belajar.id', $registrationCode->email);
        $this->assertSame('Budi Santoso', $registrationCode->name);
        $this->assertTrue(Hash::check('password-baru', $registrationCode->password_hash));
        $this->assertNotSame('password-baru', $registrationCode->password_hash);
        $this->assertSame(
            now()->addMinutes(10)->format('Y-m-d H:i:s'),
            $registrationCode->expires_at->format('Y-m-d H:i:s'),
        );

        Notification::assertSentOnDemand(
            KodeVerifikasiRegistrasiSiswa::class,
            function (
                KodeVerifikasiRegistrasiSiswa $notification,
                array $channels,
                AnonymousNotifiable $notifiable,
            ) use ($registrationCode): bool {
                return $channels === ['mail']
                    && $notifiable->routes['mail'] === 'siswa@belajar.id'
                    && preg_match('/^\d{6}$/', $notification->code) === 1
                    && Hash::check($notification->code, $registrationCode->code_hash)
                    && $notification->code !== $registrationCode->code_hash;
            },
        );
    }

    public function test_non_belajar_id_email_is_rejected(): void
    {
        Notification::fake();

        $response = $this->postJson(route('siswa-registration-code.store'), [
            'name' => 'Budi Santoso',
            'email' => 'siswa@gmail.com',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Siswa wajib menggunakan email dengan domain @belajar.id.');
        $this->assertDatabaseCount('siswa_registration_codes', 0);
        Notification::assertNothingSent();
    }

    public function test_registered_email_cannot_request_a_registration_code(): void
    {
        User::factory()->create(['email' => 'siswa@belajar.id']);
        Notification::fake();

        $response = $this->postJson(route('siswa-registration-code.store'), [
            'name' => 'Budi Santoso',
            'email' => 'siswa@belajar.id',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email belajar.id ini sudah terdaftar.');
        $this->assertDatabaseCount('siswa_registration_codes', 0);
        Notification::assertNothingSent();
    }

    public function test_password_confirmation_must_match(): void
    {
        Notification::fake();

        $response = $this->postJson(route('siswa-registration-code.store'), [
            'name' => 'Budi Santoso',
            'email' => 'siswa@belajar.id',
            'password' => 'password-baru',
            'password_confirmation' => 'password-berbeda',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Konfirmasi password tidak cocok.');
        $this->assertDatabaseCount('siswa_registration_codes', 0);
        Notification::assertNothingSent();
    }

    public function test_requesting_a_new_code_invalidates_the_previous_code(): void
    {
        Notification::fake();

        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'siswa@belajar.id',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ];

        $this->postJson(route('siswa-registration-code.store'), $payload)->assertStatus(202);
        $this->postJson(route('siswa-registration-code.store'), $payload)->assertStatus(202);

        $codes = SiswaRegistrationCode::query()->oldest('id')->get();
        $this->assertCount(2, $codes);
        $this->assertNotNull($codes[0]->used_at);
        $this->assertNull($codes[1]->used_at);
        Notification::assertSentOnDemandTimes(KodeVerifikasiRegistrasiSiswa::class, 2);
    }

    public function test_code_requests_are_rate_limited(): void
    {
        Notification::fake();

        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'dibatasi@belajar.id',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson(route('siswa-registration-code.store'), $payload)->assertStatus(202);
        }

        $this->postJson(route('siswa-registration-code.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('siswa_registration_codes', 3);
        Notification::assertSentOnDemandTimes(KodeVerifikasiRegistrasiSiswa::class, 3);
    }
}
