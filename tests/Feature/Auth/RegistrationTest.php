<?php

namespace Tests\Feature\Auth;

use App\Notifications\KodeVerifikasiRegistrasiSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_form_submission_requests_a_verification_code(): void
    {
        Notification::fake();

        $response = $this->from('/register')->post('/register', [
            'name' => 'Budi Santoso',
            'email' => 'siswa@belajar.id',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ]);

        $response
            ->assertRedirect('/register')
            ->assertSessionHas('status', 'Kode verifikasi pendaftaran telah dikirim ke email belajar.id Anda.');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        Notification::assertSentOnDemand(KodeVerifikasiRegistrasiSiswa::class);
    }
}
