<?php

namespace Tests\Feature\Auth;

use App\Models\OtpVerification;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticatedSiswaSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function code(User $user, array $attributes = []): OtpVerification
    {
        return $user->otpVerifications()->create([
            'purpose' => 'login', 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10),
            'attempts' => 0, ...$attributes,
        ]);
    }

    public function test_valid_code_authenticates_and_cannot_be_replayed(): void
    {
        $user = Siswa::factory()->create()->user;
        $code = $this->code($user);
        $this->postJson(route('siswa-login.verify'), ['email' => $user->email, 'code' => '123456'])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($code->fresh()->used_at);
        $this->post('/logout');
        $this->postJson(route('siswa-login.verify'), ['email' => $user->email, 'code' => '123456'])->assertUnprocessable();
    }

    public function test_five_wrong_attempts_are_persisted_and_disable_the_code(): void
    {
        $user = Siswa::factory()->create()->user;
        $code = $this->code($user);
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(route('siswa-login.verify'), ['email' => $user->email, 'code' => '654321'])->assertUnprocessable();
            $this->assertSame($i, $code->fresh()->attempts);
        }
        $this->assertNotNull($code->fresh()->used_at);
        $this->postJson(route('siswa-login.verify'), ['email' => $user->email, 'code' => '123456'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_expired_and_wrong_purpose_codes_do_not_authenticate(): void
    {
        $user = Siswa::factory()->create()->user;
        $this->code($user, ['expires_at' => now()]);
        $this->postJson(route('siswa-login.verify'), ['email' => $user->email, 'code' => '123456'])->assertUnprocessable();
        $this->code($user, ['purpose' => 'reset_password']);
        $this->postJson(route('siswa-login.verify'), ['email' => $user->email, 'code' => '123456'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_inactive_account_and_another_students_code_are_rejected(): void
    {
        $user = Siswa::factory()->create()->user;
        $other = Siswa::factory()->create()->user;
        $this->code($other);
        $this->postJson(route('siswa-login.verify'), ['email' => $user->email, 'code' => '123456'])->assertUnprocessable();
        $other->update(['status' => 'nonaktif']);
        $this->postJson(route('siswa-login.verify'), ['email' => $other->email, 'code' => '123456'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_code_requires_exactly_six_digits(): void
    {
        $user = Siswa::factory()->create()->user;
        $this->code($user);
        $this->postJson(route('siswa-login.verify'), ['email' => $user->email, 'code' => '12345'])->assertUnprocessable();
        $this->postJson(route('siswa-login.verify'), ['email' => $user->email, 'code' => 'abcdef'])->assertUnprocessable();
    }
}
