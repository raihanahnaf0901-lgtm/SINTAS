<?php

namespace Tests\Feature\Auth;

use App\Models\Guru;
use App\Models\OtpVerification;
use App\Models\Siswa;
use App\Notifications\KodeVerifikasiAkun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SiswaLoginCodeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_gmail_credentials_send_a_hashed_purpose_scoped_code(): void
    {
        Notification::fake();
        $user = Siswa::factory()->create()->user;
        $user->update(['email' => 'siswa@gmail.com']);
        $this->postJson(route('siswa-login-code.store'), ['email' => ' SISWA@GMAIL.COM ', 'password' => 'password'])
            ->assertAccepted()->assertJsonPath('expires_in_minutes', 10)->assertJsonPath('resend_after_seconds', 15);
        $this->assertGuest();
        $record = OtpVerification::sole();
        $this->assertSame('login', $record->purpose);
        $this->assertSame($user->id, $record->user_id);
        Notification::assertSentTo($user, KodeVerifikasiAkun::class,
            fn ($notification) => Hash::check($notification->code, $record->code_hash) && $notification->purpose === 'login');
    }

    public function test_wrong_password_does_not_issue_an_otp(): void
    {
        Notification::fake();
        $user = Siswa::factory()->create()->user;
        $this->postJson(route('siswa-login-code.store'), ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('otp_verifications', 0);
    }

    public function test_teacher_cannot_request_a_student_code(): void
    {
        Notification::fake();
        $user = Guru::factory()->create()->user;
        $this->postJson(route('siswa-login-code.store'), ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        Notification::assertNothingSent();
    }

    public function test_resend_invalidates_previous_code_and_is_rate_limited(): void
    {
        Notification::fake();
        $user = Siswa::factory()->create()->user;
        $data = ['email' => $user->email, 'password' => 'password'];
        $this->postJson(route('siswa-login-code.store'), $data)->assertAccepted();
        $first = OtpVerification::sole();
        $this->postJson(route('siswa-login-code.store'), $data)->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->assertNull($first->fresh()->used_at);
        $this->travel(16)->seconds();
        $this->postJson(route('siswa-login-code.store'), $data)->assertAccepted();
        $this->assertNotNull($first->fresh()->used_at);
        $this->travel(16)->seconds();
        $this->postJson(route('siswa-login-code.store'), $data)->assertAccepted();
        $this->travel(16)->seconds();
        $this->postJson(route('siswa-login-code.store'), $data)->assertAccepted();
        $this->assertSame(1, OtpVerification::whereNull('used_at')->count());
        Notification::assertSentToTimes($user, KodeVerifikasiAkun::class, 4);
    }

    public function test_failed_mail_delivery_invalidates_the_code(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        $user = Siswa::factory()->create()->user;
        $this->postJson(route('siswa-login-code.store'), ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->assertSame(0, OtpVerification::whereNull('used_at')->count());
    }

    public function test_failed_resend_preserves_previous_code_and_can_be_retried_immediately(): void
    {
        $user = Siswa::factory()->create()->user;
        $previous = $user->otpVerifications()->create([
            'purpose' => 'login', 'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10), 'attempts' => 0,
        ]);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        $data = ['email' => $user->email, 'password' => 'password'];

        $this->postJson(route('siswa-login-code.store'), $data)->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertNull($previous->fresh()->used_at);
        $this->assertDatabaseCount('otp_verifications', 1);
        Notification::fake();
        $this->postJson(route('siswa-login-code.store'), $data)->assertAccepted();
        $this->assertNotNull($previous->fresh()->used_at);
        Notification::assertSentToTimes($user, KodeVerifikasiAkun::class, 1);
    }
}
