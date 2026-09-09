<?php

namespace Tests\Feature\Auth;

use App\Models\Siswa;
use App\Notifications\KodeVerifikasiAkun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResetPasswordOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_otp_is_single_use_and_invalidates_outstanding_login_codes(): void
    {
        Notification::fake();
        $user = Siswa::factory()->create()->user;
        $user->otpVerifications()->create(['purpose' => 'login', 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10)]);
        $this->postJson(route('password.otp.request'), ['email' => $user->email])->assertAccepted();
        $code = Notification::sent($user, KodeVerifikasiAkun::class)->sole()->code;
        $data = ['email' => $user->email, 'code' => $code, 'password' => 'new-private-password', 'password_confirmation' => 'new-private-password'];
        $this->postJson(route('password.otp.reset'), $data)->assertOk();
        $this->assertTrue(Hash::check('new-private-password', $user->fresh()->password));
        $this->assertSame(0, $user->otpVerifications()->whereNull('used_at')->count());
        $this->postJson(route('password.otp.reset'), $data)->assertUnprocessable();
    }

    public function test_unknown_email_returns_the_same_reset_request_response(): void
    {
        Notification::fake();
        $this->postJson(route('password.otp.request'), ['email' => 'unknown@gmail.com'])->assertAccepted();
        Notification::assertNothingSent();
    }
}
