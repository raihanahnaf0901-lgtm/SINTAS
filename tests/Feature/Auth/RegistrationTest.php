<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\KodeVerifikasiAkun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_new_student_is_pending_until_registration_otp_is_verified(): void
    {
        Notification::fake();
        $this->postJson('/register', ['name' => 'Siswa Baru', 'email' => 'baru@gmail.com',
            'password' => 'private-password', 'password_confirmation' => 'private-password', 'role' => 'guru', 'status' => 'aktif'])
            ->assertAccepted();
        $this->assertGuest();
        $user = User::where('email', 'baru@gmail.com')->sole();
        $this->assertSame('siswa', $user->role);
        $this->assertSame('pending', $user->status);
        $this->assertNotNull($user->siswa);
        $this->assertNull($user->siswa->kelas_id);
        $code = Notification::sent($user, KodeVerifikasiAkun::class)->sole()->code;
        $this->postJson(route('siswa-registration.verify'), ['email' => $user->email, 'code' => $code])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('aktif', $user->fresh()->status);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->getJson('/api/v1/dashboard')->assertJsonCount(0, 'data.subjects')->assertJsonPath('data.summaries.0.total', 0);
        $this->patchJson('/api/v1/profil/siswa', ['nama_lengkap' => 'Nama Siswa', 'nis' => '00012345', 'nisn' => '0000123456'])
            ->assertOk()->assertJsonPath('data.siswa.nis', '00012345');
        $this->assertSame('Nama Siswa', $user->fresh()->name);
    }

    public function test_register_cannot_override_an_existing_active_account(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'lama@gmail.com']);
        $original = $user->password;
        $this->postJson('/register', ['name' => 'Attacker', 'email' => $user->email,
            'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertUnprocessable();
        $this->assertSame($original, $user->fresh()->password);
        Notification::assertNothingSent();
    }
}
