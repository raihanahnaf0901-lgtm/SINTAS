<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\KeanggotaanSekolah;
use App\Models\KelasMapel;
use App\Models\Sekolah;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SchoolMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_teacher_can_open_school_onboarding_but_not_admin_or_create_class(): void
    {
        $guru = Guru::factory()->create();

        $this->actingAs($guru->user)->get('/data-sekolah')->assertInertia(fn (Assert $page) => $page
            ->component('School')->where('auth.canCreateClass', false)->where('auth.isSchoolAdmin', false));
        $this->get('/admin-sekolah')->assertForbidden();
        $this->getJson('/api/v1/sekolah')->assertJsonPath('membership', null);
        $this->postJson('/api/v1/kelas-mapel', ['nama_kelas_mapel' => 'Matematika'])->assertUnprocessable();
        $this->assertDatabaseCount('kelas_mapel', 0);
    }

    public function test_registration_creates_pending_school_and_uses_server_plan_price_not_client_price(): void
    {
        Http::preventStrayRequests();
        config(['school-subscriptions.server_key' => 'SB-test-key', 'school-subscriptions.sandbox' => true,
            'school-subscriptions.plans' => ['test' => ['name' => 'Paket uji', 'amount' => 10000, 'duration_days' => 30]]]);
        Http::fake(['https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'test-token', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/test-token',
        ])]);
        $guru = Guru::factory()->create();

        $response = $this->actingAs($guru->user)->postJson('/api/v1/sekolah', [
            'nama_sekolah' => 'SMK Contoh', 'npsn' => '01234567', 'plan_code' => 'test',
            'status' => 'aktif', 'admin_guru_id' => 999, 'amount' => 1, 'role' => 'admin_sekolah',
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('payment.amount', 10000);

        $this->assertDatabaseHas('sekolah', ['id' => $response->json('data.id'), 'admin_guru_id' => $guru->id, 'status' => 'pending']);
        $this->assertDatabaseHas('keanggotaan_sekolah', ['guru_id' => $guru->id, 'status' => 'pending']);
        $this->assertFalse($guru->user->fresh()->hasRole('admin_sekolah'));
        Http::assertSent(fn ($request) => $request['transaction_details']['gross_amount'] === 10000);
        $this->postJson('/api/v1/sekolah', [
            'nama_sekolah' => 'Sekolah kedua', 'npsn' => '11111111', 'plan_code' => 'test',
        ])->assertCreated()->assertJsonPath('payment.amount', 10000);
        $this->assertDatabaseCount('sekolah', 2);
        $this->assertDatabaseCount('keanggotaan_sekolah', 2);
    }

    public function test_registration_is_blocked_when_payment_is_not_configured(): void
    {
        Http::preventStrayRequests();
        config(['school-subscriptions.server_key' => '',
            'school-subscriptions.plans' => ['test' => ['name' => 'Paket uji', 'amount' => 10000, 'duration_days' => 30]]]);

        $this->actingAs(Guru::factory()->create()->user)->postJson('/api/v1/sekolah', [
            'nama_sekolah' => 'SMK Contoh', 'npsn' => '01234567', 'plan_code' => 'test',
        ])->assertUnprocessable()->assertJsonValidationErrors('payment');

        $this->assertDatabaseCount('sekolah', 0);
        Http::assertNothingSent();
    }

    public function test_school_registration_validates_identity_and_requires_configured_plan(): void
    {
        config(['school-subscriptions.plans' => []]);

        $this->actingAs(Guru::factory()->create()->user)->postJson('/api/v1/sekolah', [
            'npsn' => '123', 'plan_code' => 'free',
        ])->assertUnprocessable()->assertJsonValidationErrors(['nama_sekolah', 'npsn', 'plan_code'])
            ->assertJsonPath('errors.npsn.0', 'NPSN harus terdiri dari 8 angka.');

        $this->assertDatabaseCount('sekolah', 0);
    }

    public function test_teacher_must_wait_for_owner_approval_then_can_create_school_class(): void
    {
        $school = Sekolah::factory()->withAdmin()->create();
        $guru = Guru::factory()->create(['jenis_guru' => 'guru_piket']);
        $response = $this->actingAs($guru->user)->postJson('/api/v1/sekolah/gabung', [
            'kode_sekolah' => strtolower($school->kode_sekolah), 'status' => 'diterima',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');
        $memberId = $response->json('data.id');
        $this->postJson('/api/v1/kelas-mapel', ['nama_kelas_mapel' => 'Kelas belum boleh'])->assertForbidden();

        $this->actingAs($school->admin->user)->patchJson('/api/v1/admin-sekolah/anggota/'.$memberId, [
            'status' => 'diterima',
        ])->assertOk();

        $this->assertDatabaseHas('keanggotaan_sekolah', ['id' => $memberId, 'status' => 'diterima', 'reviewed_by' => $school->admin_guru_id]);
        $this->assertDatabaseHas('keanggotaan_sekolah', ['guru_id' => $guru->id, 'sekolah_id' => $school->id, 'jenis_guru' => 'guru_mapel']);
        $this->actingAs($guru->user->fresh())->postJson('/api/v1/kelas-mapel', [
            'nama_kelas_mapel' => 'Matematika',
        ])->assertCreated()->assertJsonPath('data.sekolah_id', $school->id);
        $this->assertFalse($guru->user->fresh()->hasRole('admin_sekolah'));
    }

    public function test_pending_and_accepted_teachers_cannot_join_a_second_school(): void
    {
        $schools = Sekolah::factory()->count(2)->create();
        $guru = Guru::factory()->create();
        $this->actingAs($guru->user)->postJson('/api/v1/sekolah/gabung', [
            'kode_sekolah' => $schools[0]->kode_sekolah,
        ])->assertCreated();
        $this->postJson('/api/v1/sekolah/gabung', ['kode_sekolah' => $schools[1]->kode_sekolah])
            ->assertUnprocessable()->assertJsonValidationErrors('kode_sekolah');
        $guru->keanggotaanSekolah()->update(['status' => 'diterima']);
        $this->postJson('/api/v1/sekolah/gabung', ['kode_sekolah' => $schools[1]->kode_sekolah])->assertUnprocessable();

        $this->assertDatabaseCount('keanggotaan_sekolah', 1);
        $this->assertDatabaseHas('keanggotaan_sekolah', ['guru_id' => $guru->id, 'sekolah_id' => $schools[0]->id]);
    }

    public function test_approving_teacher_makes_preexisting_owned_classes_visible_to_school_admin(): void
    {
        $school = Sekolah::factory()->withAdmin()->create();
        $legacy = KelasMapel::factory()->create();
        $member = KeanggotaanSekolah::factory()->create(['sekolah_id' => $school->id, 'guru_id' => $legacy->guru_pembuat_id]);

        $this->actingAs($school->admin->user)->patchJson('/api/v1/admin-sekolah/anggota/'.$member->id, ['status' => 'diterima'])->assertOk();

        $this->assertDatabaseHas('kelas_mapel', ['id' => $legacy->id, 'sekolah_id' => $school->id]);
        $this->getJson('/api/v1/kelas-mapel/'.$legacy->id)->assertOk();
        $this->patchJson('/api/v1/kelas-mapel/'.$legacy->id, ['nama_kelas_mapel' => 'Dikelola admin sekolah'])->assertOk();
    }

    public function test_rejected_or_cancelled_applicant_can_choose_another_school(): void
    {
        $school = Sekolah::factory()->withAdmin()->create();
        $other = Sekolah::factory()->create();
        $member = KeanggotaanSekolah::factory()->create(['sekolah_id' => $school->id]);

        $this->actingAs($school->admin->user)->patchJson('/api/v1/admin-sekolah/anggota/'.$member->id, ['status' => 'ditolak'])->assertOk();
        $this->actingAs($member->guru->user)->postJson('/api/v1/sekolah/gabung', ['kode_sekolah' => $other->kode_sekolah])->assertCreated();
        $this->deleteJson('/api/v1/sekolah/permintaan', ['sekolah_id' => $other->id])->assertOk();
        $this->postJson('/api/v1/sekolah/gabung', ['kode_sekolah' => $school->kode_sekolah])->assertCreated();

        $this->assertDatabaseCount('keanggotaan_sekolah', 3);
        $this->assertDatabaseHas('keanggotaan_sekolah', ['guru_id' => $member->guru_id, 'sekolah_id' => $school->id, 'status' => 'pending']);
    }

    public function test_admin_only_manages_teachers_and_requests_inside_their_school(): void
    {
        $school = Sekolah::factory()->withAdmin()->create();
        $own = Guru::factory()->inSchool($school)->create();
        $foreign = KeanggotaanSekolah::factory()->accepted()->create();
        $pending = KeanggotaanSekolah::factory()->create();
        $ordinary = Guru::factory()->inSchool($school)->create();

        $this->actingAs($school->admin->user)->patchJson('/api/v1/admin-sekolah/guru/'.$own->id, ['jenis_guru' => 'guru_piket'])->assertOk();
        $this->patchJson('/api/v1/admin-sekolah/guru/'.$foreign->guru_id, ['jenis_guru' => 'guru_piket'])->assertNotFound();
        $this->patchJson('/api/v1/admin-sekolah/anggota/'.$pending->id, ['status' => 'diterima'])->assertNotFound();
        $this->patchJson('/api/v1/admin-sekolah/guru/'.$own->id, ['jenis_guru' => 'admin_sekolah'])->assertUnprocessable();
        $this->actingAs($ordinary->user)->patchJson('/api/v1/admin-sekolah/guru/'.$own->id, ['jenis_guru' => 'guru_mapel'])->assertForbidden();

        $this->assertDatabaseHas('keanggotaan_sekolah', ['guru_id' => $own->id, 'sekolah_id' => $school->id, 'jenis_guru' => 'guru_piket']);
        $this->assertDatabaseHas('guru', ['id' => $foreign->guru_id, 'jenis_guru' => 'guru_mapel']);
        $this->assertDatabaseHas('keanggotaan_sekolah', ['id' => $pending->id, 'status' => 'pending']);
    }

    public function test_school_admin_dashboard_excludes_other_schools_and_does_not_accept_return_url_as_payment(): void
    {
        $school = Sekolah::factory()->withAdmin()->create();
        $own = KelasMapel::factory()->create(['sekolah_id' => $school->id, 'guru_pembuat_id' => Guru::factory()->inSchool($school)]);
        $foreign = KelasMapel::factory()->create(['sekolah_id' => Sekolah::factory()]);
        $this->actingAs($school->admin->user)->getJson('/api/v1/admin-sekolah')->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.id', $own->id)->assertJsonMissing(['nama_kelas_mapel' => $foreign->nama_kelas_mapel]);
        $this->get('/admin-sekolah')->assertInertia(fn (Assert $page) => $page->component('SchoolAdmin')->where('auth.isSchoolAdmin', true));

        $pending = Sekolah::factory()->pending()->create();
        KeanggotaanSekolah::factory()->create(['sekolah_id' => $pending->id, 'guru_id' => $pending->admin_guru_id]);
        $this->actingAs($pending->admin->user)->get('/data-sekolah?transaction_status=settlement&status_code=200')->assertOk();
        $this->assertDatabaseHas('sekolah', ['id' => $pending->id, 'status' => 'pending']);
        $this->assertFalse($pending->admin->user->fresh()->hasRole('admin_sekolah'));
    }

    public function test_owner_cannot_cancel_pending_registration_and_members_cannot_cancel_accepted_membership(): void
    {
        $school = Sekolah::factory()->pending()->create();
        KeanggotaanSekolah::factory()->create(['sekolah_id' => $school->id, 'guru_id' => $school->admin_guru_id]);
        $this->actingAs($school->admin->user)->deleteJson('/api/v1/sekolah/permintaan', ['sekolah_id' => $school->id])->assertUnprocessable();
        $accepted = Guru::factory()->inSchool()->create();
        $this->actingAs($accepted->user)->deleteJson('/api/v1/sekolah/permintaan', ['sekolah_id' => $accepted->sekolahAktif()->id])->assertUnprocessable();

        $this->assertDatabaseHas('keanggotaan_sekolah', ['guru_id' => $school->admin_guru_id, 'status' => 'pending']);
        $this->assertDatabaseHas('keanggotaan_sekolah', ['guru_id' => $accepted->id, 'status' => 'diterima']);
    }

    public function test_expired_and_unpaid_schools_do_not_accept_join_requests(): void
    {
        $this->freezeTime();
        $expired = Sekolah::factory()->create(['subscription_ends_at' => now()->subSecond()]);
        $pending = Sekolah::factory()->pending()->create();
        $this->actingAs(Guru::factory()->create()->user);

        foreach ([$expired, $pending] as $school) {
            $this->postJson('/api/v1/sekolah/gabung', ['kode_sekolah' => $school->kode_sekolah])->assertUnprocessable()
                ->assertJsonPath('errors.kode_sekolah.0', 'Kode sekolah tidak tersedia atau langganan sekolah belum aktif.');
        }

        $this->assertDatabaseCount('keanggotaan_sekolah', 0);
    }

    public function test_students_and_guests_cannot_use_school_administration(): void
    {
        $this->getJson('/api/v1/sekolah')->assertUnauthorized();
        $this->get('/admin-sekolah')->assertRedirectToRoute('login');
        $this->actingAs(Siswa::factory()->create()->user)->get('/data-sekolah')->assertForbidden();
        $this->getJson('/api/v1/admin-sekolah')->assertForbidden();
        $this->postJson('/api/v1/sekolah/gabung', ['kode_sekolah' => 'SCHOOL'])->assertForbidden();
        $this->postJson('/api/v1/sekolah', [])->assertForbidden();
    }
}
