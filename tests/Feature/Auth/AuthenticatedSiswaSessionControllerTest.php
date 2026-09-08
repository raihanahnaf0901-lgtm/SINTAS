<?php

namespace Tests\Feature\Auth;

use App\Models\Siswa;
use App\Models\SiswaLoginCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticatedSiswaSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_code_authenticates_the_student_and_consumes_the_code(): void
    {
        $user = $this->createStudent();
        $loginCode = SiswaLoginCode::factory()->for($user)->create([
            'code_hash' => Hash::make('123456'),
        ]);

        $response = $this->postJson(route('siswa-login.verify'), [
            'email' => ' SISWA@BELAJAR.ID ',
            'code' => '123456',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Verifikasi berhasil. Anda telah masuk sebagai siswa.')
            ->assertJsonPath('redirect', '/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($loginCode->refresh()->used_at);
    }

    public function test_wrong_code_increments_attempts_without_authenticating(): void
    {
        $user = $this->createStudent();
        $loginCode = SiswaLoginCode::factory()->for($user)->create([
            'code_hash' => Hash::make('123456'),
        ]);

        $response = $this->postJson(route('siswa-login.verify'), [
            'email' => $user->email,
            'code' => '654321',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Kode verifikasi salah, kedaluwarsa, atau sudah digunakan.');
        $this->assertGuest();
        $this->assertSame(1, $loginCode->refresh()->attempts);
        $this->assertNull($loginCode->used_at);
    }

    public function test_fifth_wrong_attempt_invalidates_the_code(): void
    {
        $user = $this->createStudent();
        $loginCode = SiswaLoginCode::factory()->for($user)->create([
            'code_hash' => Hash::make('123456'),
            'attempts' => 4,
        ]);

        $this->postJson(route('siswa-login.verify'), [
            'email' => $user->email,
            'code' => '654321',
        ])->assertUnprocessable();

        $this->assertSame(5, $loginCode->refresh()->attempts);
        $this->assertNotNull($loginCode->used_at);

        $this->postJson(route('siswa-login.verify'), [
            'email' => $user->email,
            'code' => '123456',
        ])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_expired_code_cannot_authenticate_the_student(): void
    {
        $user = $this->createStudent();
        $loginCode = SiswaLoginCode::factory()->expired()->for($user)->create();

        $response = $this->postJson(route('siswa-login.verify'), [
            'email' => $user->email,
            'code' => '123456',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->assertGuest();
        $this->assertNotNull($loginCode->refresh()->used_at);
    }

    public function test_used_code_cannot_be_replayed(): void
    {
        $user = $this->createStudent();
        SiswaLoginCode::factory()->used()->for($user)->create();

        $response = $this->postJson(route('siswa-login.verify'), [
            'email' => $user->email,
            'code' => '123456',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertGuest();
    }

    public function test_code_must_contain_exactly_six_digits(): void
    {
        $response = $this->postJson(route('siswa-login.verify'), [
            'email' => 'siswa@belajar.id',
            'code' => 'ABC12',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Kode verifikasi harus terdiri dari 6 angka.');
        $this->assertGuest();
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
