<?php

namespace Tests\Feature\Auth;

use App\Models\Siswa;
use App\Models\SiswaRegistrationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisteredSiswaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_code_creates_and_authenticates_a_verified_student(): void
    {
        SiswaRegistrationCode::factory()->create([
            'email' => 'siswa@belajar.id',
            'name' => 'Nama Sementara',
            'password_hash' => Hash::make('password-private'),
            'code_hash' => Hash::make('123456'),
        ]);

        $response = $this->postJson(route('siswa-registration.verify'), [
            'email' => ' SISWA@BELAJAR.ID ',
            'code' => '123456',
            'name' => '  Budi   Santoso  ',
            'nisn' => '0012345678',
            'role' => 'admin',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Akun siswa berhasil dibuat dan email telah diverifikasi.')
            ->assertJsonPath('redirect', '/dashboard')
            ->assertJsonPath('user.name', 'Budi Santoso')
            ->assertJsonPath('user.email', 'siswa@belajar.id')
            ->assertJsonPath('user.role', 'siswa')
            ->assertJsonPath('user.nisn', '0012345678');

        $user = User::query()->where('email', 'siswa@belajar.id')->sole();
        $this->assertSame('siswa', $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password-private', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('siswas', [
            'user_id' => $user->id,
            'nama_lengkap' => 'Budi Santoso',
            'nisn' => '0012345678',
            'status' => 'aktif',
        ]);
        $this->assertDatabaseCount('siswa_registration_codes', 0);
    }

    public function test_name_from_the_first_step_is_used_when_verification_does_not_resend_it(): void
    {
        SiswaRegistrationCode::factory()->create([
            'email' => 'siswa@belajar.id',
            'name' => 'Budi Santoso',
        ]);

        $response = $this->postJson(route('siswa-registration.verify'), [
            'email' => 'siswa@belajar.id',
            'code' => '123456',
            'nisn' => '0012345678',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'Budi Santoso');
        $this->assertDatabaseHas('siswas', [
            'nama_lengkap' => 'Budi Santoso',
            'nisn' => '0012345678',
        ]);
    }

    public function test_wrong_code_increments_attempts_without_creating_an_account(): void
    {
        $registrationCode = SiswaRegistrationCode::factory()->create([
            'email' => 'siswa@belajar.id',
            'code_hash' => Hash::make('123456'),
        ]);

        $response = $this->postJson(route('siswa-registration.verify'), [
            'email' => 'siswa@belajar.id',
            'code' => '654321',
            'name' => 'Budi Santoso',
            'nisn' => '0012345678',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Kode verifikasi salah, kedaluwarsa, atau sudah digunakan.');
        $this->assertGuest();
        $this->assertSame(1, $registrationCode->refresh()->attempts);
        $this->assertNull($registrationCode->used_at);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('siswas', 0);
    }

    public function test_fifth_wrong_attempt_invalidates_the_code(): void
    {
        $registrationCode = SiswaRegistrationCode::factory()->create([
            'email' => 'siswa@belajar.id',
            'code_hash' => Hash::make('123456'),
            'attempts' => 4,
        ]);

        $this->postJson(route('siswa-registration.verify'), [
            'email' => 'siswa@belajar.id',
            'code' => '654321',
            'name' => 'Budi Santoso',
            'nisn' => '0012345678',
        ])->assertUnprocessable();

        $this->assertSame(5, $registrationCode->refresh()->attempts);
        $this->assertNotNull($registrationCode->used_at);
        $this->assertGuest();
    }

    public function test_expired_code_cannot_create_an_account(): void
    {
        $registrationCode = SiswaRegistrationCode::factory()->expired()->create([
            'email' => 'siswa@belajar.id',
        ]);

        $response = $this->postJson(route('siswa-registration.verify'), [
            'email' => 'siswa@belajar.id',
            'code' => '123456',
            'name' => 'Budi Santoso',
            'nisn' => '0012345678',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertNotNull($registrationCode->refresh()->used_at);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_nisn_is_rejected_after_code_verification(): void
    {
        Siswa::factory()->create(['nisn' => '0012345678']);
        SiswaRegistrationCode::factory()->create([
            'email' => 'siswa-baru@belajar.id',
        ]);

        $response = $this->postJson(route('siswa-registration.verify'), [
            'email' => 'siswa-baru@belajar.id',
            'code' => '123456',
            'name' => 'Budi Santoso',
            'nisn' => '0012345678',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.nisn.0', 'NISN ini sudah terdaftar.');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'siswa-baru@belajar.id']);
    }

    public function test_nisn_must_contain_exactly_ten_digits(): void
    {
        $response = $this->postJson(route('siswa-registration.verify'), [
            'email' => 'siswa@belajar.id',
            'code' => '123456',
            'name' => 'Budi Santoso',
            'nisn' => 'ABC123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.nisn.0', 'NISN harus terdiri dari 10 angka.');
        $this->assertGuest();
    }
}
