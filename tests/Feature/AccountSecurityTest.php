<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_name_is_synchronized_and_email_change_invalidates_outstanding_codes(): void
    {
        $siswa = Siswa::factory()->create();
        $code = $siswa->user->otpVerifications()->create([
            'purpose' => 'login', 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10),
        ]);
        $this->actingAs($siswa->user)->patch('/profile', ['name' => 'Nama Baru', 'email' => 'baru@gmail.com',
            'nis' => '00001234', 'nisn' => '0000001234'])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', $siswa->fresh()->nama_lengkap);
        $this->assertSame('00001234', $siswa->fresh()->nis);
        $this->assertNull($siswa->user->fresh()->email_verified_at);
        $this->assertNotNull($code->fresh()->used_at);
    }

    public function test_password_change_revokes_codes_and_cannot_change_role(): void
    {
        $siswa = Siswa::factory()->create();
        $code = $siswa->user->otpVerifications()->create([
            'purpose' => 'login', 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10),
        ]);
        $this->actingAs($siswa->user)->put('/password', ['current_password' => 'password',
            'password' => 'new-private-password', 'password_confirmation' => 'new-private-password', 'role' => 'guru'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($code->fresh()->used_at);
        $this->assertSame('siswa', $siswa->user->fresh()->role);
    }

    public function test_teacher_account_cannot_be_deleted_along_with_academic_history(): void
    {
        $guru = Guru::factory()->create();
        $this->actingAs($guru->user)->delete('/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertNotNull($guru->user->fresh());
    }
}
