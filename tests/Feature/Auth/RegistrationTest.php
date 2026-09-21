<?php

namespace Tests\Feature\Auth;

use App\Models\Guru;
use App\Models\KelasMapel;
use App\Models\Siswa;
use App\Models\User;
use App\Notifications\KodeVerifikasiAkun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
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

    #[TestWith(['siswa'])]
    #[TestWith(['guru'])]
    public function test_registration_cannot_activate_or_login_without_a_valid_otp(string $role): void
    {
        Notification::fake();
        $data = $this->teacherData(['email' => 'belum.verifikasi@gmail.com', 'status' => 'aktif', 'email_verified_at' => now()->toDateTimeString()]);
        $this->postJson(route($role.'-registration-code.store'), $data)->assertAccepted();
        $user = User::where('email', $data['email'])->sole();
        $code = Notification::sent($user, KodeVerifikasiAkun::class)->sole()->code;

        foreach (['', $code === '000000' ? '111111' : '000000'] as $invalidCode) {
            $this->postJson(route($role.'-registration.verify'), ['email' => $user->email, 'code' => $invalidCode])
                ->assertUnprocessable()->assertJsonValidationErrors('code');
            $this->assertGuest();
            $this->assertSame('pending', $user->fresh()->status);
            $this->assertNull($user->fresh()->email_verified_at);
        }
        $this->postJson(route('login'), ['role' => $role, 'email' => $user->email, 'password' => $data['password']])->assertUnprocessable();
        $this->assertGuest();
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
        $this->get('/dashboard')->assertRedirectToRoute('login');
        Notification::assertSentToTimes($user, KodeVerifikasiAkun::class, 1);
    }

    #[TestWith(['siswa'])]
    #[TestWith(['guru'])]
    public function test_failed_registration_email_does_not_leave_a_new_account(string $role): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $data = $this->teacherData(['email' => 'gagal.kirim@gmail.com']);

        $this->postJson(route($role.'-registration-code.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
        $this->assertDatabaseCount('siswa', 0);
        $this->assertDatabaseCount('guru', 0);
        $this->assertDatabaseCount('otp_verifications', 0);
    }

    public function test_user_created_without_explicit_status_is_not_active(): void
    {
        $user = User::create(['name' => 'Belum Aktif', 'email' => 'pending@gmail.com', 'password' => 'private-password']);

        $this->assertSame('pending', $user->status);
        $this->assertNull($user->email_verified_at);
    }

    public function test_registration_page_can_open_with_teacher_selected(): void
    {
        $this->get('/register?role=guru')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')->where('initialRole', 'guru'));
        $this->get('/register?role=admin')->assertInertia(fn (Assert $page) => $page->where('initialRole', 'siswa'));
    }

    #[TestWith(['guru_mapel'])]
    #[TestWith(['guru_piket'])]
    public function test_teacher_registers_with_profile_and_only_gets_access_after_otp(string $type): void
    {
        Notification::fake();
        $otherClass = KelasMapel::factory()->create();
        $this->postJson(route('guru-registration-code.store'), $this->teacherData([
            'email' => ' GURU.BARU@GMAIL.COM ', 'jenis_guru' => $type, 'role' => 'admin', 'status' => 'aktif',
        ]))->assertAccepted();

        $user = User::where('email', 'guru.baru@gmail.com')->sole();
        $this->assertSame('guru', $user->role);
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->siswa);
        $this->assertTrue(Hash::check('private-password', $user->password));
        $this->assertDatabaseHas('guru', ['user_id' => $user->id, 'nama_lengkap' => 'Guru Baru',
            'nip' => '001234567890123456', 'gelar' => 'S.Pd.', 'jenis_guru' => $type]);
        $this->assertGuest();
        $this->postJson(route('login'), ['role' => 'guru', 'email' => $user->email, 'password' => 'private-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $code = Notification::sent($user, KodeVerifikasiAkun::class)->sole()->code;
        $this->postJson(route('guru-registration.verify'), ['email' => $user->email, 'code' => $code])
            ->assertOk()->assertJsonPath('needs_profile', false)->assertJsonPath('redirect', '/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('aktif', $user->fresh()->status);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('TeacherDashboard'));
        $this->getJson('/api/v1/kelas-mapel/'.$otherClass->id)->assertForbidden();
        $this->assertSame($type === 'guru_mapel', $user->fresh()->can('create', KelasMapel::class));
        $this->post(route('logout'))->assertRedirect('/');
        $this->post(route('login'), ['role' => 'guru', 'email' => $user->email, 'password' => 'private-password'])
            ->assertRedirectToRoute('dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_teacher_registration_rejects_duplicate_nip_without_creating_account(): void
    {
        Notification::fake();
        $existing = Guru::factory()->create(['nip' => '001234567890123456']);

        $this->postJson(route('guru-registration-code.store'), $this->teacherData())->assertUnprocessable()
            ->assertJsonPath('errors.nip.0', 'NIP sudah digunakan oleh akun guru lain.');

        $this->assertDatabaseMissing('users', ['email' => 'guru.baru@gmail.com']);
        $this->assertSame($existing->user_id, Guru::where('nip', $existing->nip)->sole()->user_id);
        Notification::assertNothingSent();
    }

    public function test_teacher_registration_validates_required_profile_and_type(): void
    {
        Notification::fake();
        $this->postJson(route('guru-registration-code.store'), $this->teacherData([
            'nip' => '', 'jenis_guru' => 'admin', 'gelar' => str_repeat('a', 51),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['nip', 'jenis_guru', 'gelar']);
        $this->assertDatabaseMissing('users', ['email' => 'guru.baru@gmail.com']);
        Notification::assertNothingSent();
    }

    public function test_pending_teacher_can_correct_profile_and_resend_without_duplicate_records(): void
    {
        Notification::fake();
        $this->postJson(route('guru-registration-code.store'), $this->teacherData())->assertAccepted();
        $user = User::where('email', 'guru.baru@gmail.com')->sole();
        $firstOtp = $user->otpVerifications()->sole();
        $this->travel(16)->seconds();

        $this->postJson(route('guru-registration-code.store'), $this->teacherData([
            'name' => 'Nama Diperbaiki', 'nip' => '001234567890123457', 'gelar' => '',
        ]))->assertAccepted();

        $this->assertSame('Nama Diperbaiki', $user->fresh()->name);
        $this->assertDatabaseHas('guru', ['user_id' => $user->id, 'nama_lengkap' => 'Nama Diperbaiki', 'nip' => '001234567890123457', 'gelar' => null]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('guru', 1);
        $this->assertNotNull($firstOtp->fresh()->used_at);
        Notification::assertSentToTimes($user, KodeVerifikasiAkun::class, 2);
    }

    public function test_teacher_signup_does_not_take_over_an_existing_student_or_teacher(): void
    {
        Notification::fake();
        $student = Siswa::factory()->create()->user;
        $teacher = Guru::factory()->create()->user;
        foreach ([$student, $teacher] as $user) {
            $original = $user->getAttributes();
            $this->postJson(route('guru-registration-code.store'), $this->teacherData(['email' => $user->email]))
                ->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->assertSame($original, $user->fresh()->getAttributes());
        }
        Notification::assertNothingSent();
    }

    public function test_teacher_verification_rejects_wrong_role_wrong_code_expiry_and_replay(): void
    {
        $this->freezeTime();
        Notification::fake();
        $this->postJson(route('guru-registration-code.store'), $this->teacherData())->assertAccepted();
        $user = User::where('email', 'guru.baru@gmail.com')->sole();
        $code = Notification::sent($user, KodeVerifikasiAkun::class)->sole()->code;
        $this->postJson(route('siswa-registration.verify'), ['email' => $user->email, 'code' => $code])->assertUnprocessable();
        $wrongCode = $code === '000000' ? '111111' : '000000';
        $this->postJson(route('guru-registration.verify'), ['email' => $user->email, 'code' => $wrongCode])->assertUnprocessable();
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertGuest();
        $this->travel(11)->minutes();
        $this->postJson(route('guru-registration.verify'), ['email' => $user->email, 'code' => $code])->assertUnprocessable();
        $this->assertGuest();
        $this->postJson(route('guru-registration-code.store'), $this->teacherData())->assertAccepted();
        $newCode = Notification::sent($user, KodeVerifikasiAkun::class)->last()->code;
        $this->postJson(route('guru-registration.verify'), ['email' => $user->email, 'code' => $newCode])->assertOk();
        $this->post(route('logout'))->assertRedirect('/');
        $this->postJson(route('guru-registration.verify'), ['email' => $user->email, 'code' => $newCode])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_pending_teacher_profile_cannot_be_changed_without_correct_password(): void
    {
        Notification::fake();
        $this->postJson(route('guru-registration-code.store'), $this->teacherData())->assertAccepted();
        $user = User::where('email', 'guru.baru@gmail.com')->sole();
        $this->postJson(route('guru-registration-code.store'), $this->teacherData([
            'password' => 'wrong-password', 'password_confirmation' => 'wrong-password', 'name' => 'Attacker',
        ]))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame('Guru Baru', $user->fresh()->name);
        $this->assertSame('Guru Baru', $user->guru->nama_lengkap);
        $this->assertDatabaseCount('otp_verifications', 1);
        Notification::assertSentToTimes($user, KodeVerifikasiAkun::class, 1);
    }

    private function teacherData(array $overrides = []): array
    {
        return [...['name' => 'Guru Baru', 'email' => 'guru.baru@gmail.com', 'nip' => '001234567890123456',
            'gelar' => 'S.Pd.', 'jenis_guru' => 'guru_mapel', 'password' => 'private-password',
            'password_confirmation' => 'private-password'], ...$overrides];
    }
}
