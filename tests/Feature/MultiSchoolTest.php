<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\KeanggotaanSekolah;
use App\Models\KelasMapel;
use App\Models\Sekolah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MultiSchoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_selection_changes_admin_context_but_not_resource_authority(): void
    {
        $first = Sekolah::factory()->withAdmin()->create();
        $second = Sekolah::factory()->withAdmin()->create(['admin_guru_id' => $first->admin_guru_id]);
        $other = Sekolah::factory()->withAdmin()->create();
        $room = KelasMapel::factory()->create(['sekolah_id' => $first->id, 'guru_pembuat_id' => Guru::factory()->inSchool($first)]);
        $foreignRoom = KelasMapel::factory()->create(['sekolah_id' => $other->id]);

        $this->actingAs($first->admin->user)->postJson('/api/v1/sekolah/pilih', ['sekolah_id' => $second->id])->assertOk();
        $this->getJson('/api/v1/sekolah')->assertJsonPath('membership.sekolah.id', $second->id)
            ->assertJsonCount(2, 'memberships')->assertJsonPath('membership.sekolah.is_admin', true);
        $this->getJson('/api/v1/admin-sekolah')->assertJsonPath('school.id', $second->id);
        $this->get('/data-sekolah')->assertInertia(fn (Assert $page) => $page
            ->where('auth.school.id', $second->id)->where('auth.isSchoolAdmin', true)->where('auth.canCreateClass', true));
        $this->patchJson('/api/v1/kelas-mapel/'.$room->id, ['nama_kelas_mapel' => 'Dikelola pendiri'])->assertOk();
        $this->patchJson('/api/v1/kelas-mapel/'.$foreignRoom->id, ['nama_kelas_mapel' => 'Tidak sah'])->assertForbidden();
        $this->postJson('/api/v1/sekolah/pilih', ['sekolah_id' => $other->id])->assertNotFound();
        $this->getJson('/api/v1/sekolah')->assertJsonPath('membership.sekolah.id', $second->id);
    }

    public function test_new_school_checkout_failure_preserves_paid_school_and_selects_new_pending_school(): void
    {
        Http::preventStrayRequests();
        config(['school-subscriptions.server_key' => 'SB-test', 'school-subscriptions.sandbox' => true]);
        Http::fake(['https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([], 503)]);
        $first = Sekolah::factory()->withAdmin()->create();
        $expires = $first->subscription_ends_at->toDateTimeString();

        $this->actingAs($first->admin->user)->postJson('/api/v1/sekolah', [
            'nama_sekolah' => 'Sekolah tambahan', 'npsn' => '87654321', 'plan_code' => 'bulanan',
        ])->assertUnprocessable()->assertJsonValidationErrors('payment');

        $second = Sekolah::query()->where('npsn', '87654321')->firstOrFail();
        $this->getJson('/api/v1/sekolah')->assertJsonCount(2, 'memberships')
            ->assertJsonPath('membership.sekolah.id', $second->id)
            ->assertJsonPath('membership.sekolah.is_admin', false)
            ->assertJsonPath('payments.0.amount', 75000)->assertJsonPath('payments.0.duration_months', 1);
        $this->assertDatabaseHas('keanggotaan_sekolah', ['guru_id' => $first->admin_guru_id, 'sekolah_id' => $first->id, 'status' => 'diterima']);
        $this->assertDatabaseHas('sekolah', ['id' => $first->id, 'status' => 'aktif', 'subscription_ends_at' => $expires]);
        $this->get('/data-sekolah')->assertInertia(fn (Assert $page) => $page
            ->where('auth.canCreateClass', false)->where('auth.isSchoolAdmin', false));
        $this->postJson('/api/v1/sekolah/pilih', ['sekolah_id' => $first->id])->assertOk();
        $this->getJson('/api/v1/sekolah')->assertJsonPath('membership.sekolah.is_admin', true)->assertJsonCount(0, 'payments');
    }

    public function test_member_role_is_scoped_to_joined_school_and_does_not_remove_own_school_authority(): void
    {
        $own = Sekolah::factory()->withAdmin()->create();
        $employer = Sekolah::factory()->withAdmin()->create();
        KeanggotaanSekolah::factory()->accepted()->create(['guru_id' => $own->admin_guru_id, 'sekolah_id' => $employer->id]);

        $this->actingAs($employer->admin->user)->patchJson('/api/v1/admin-sekolah/guru/'.$own->admin_guru_id, [
            'sekolah_id' => $employer->id, 'jenis_guru' => 'guru_piket',
        ])->assertOk();
        $this->assertDatabaseHas('guru', ['id' => $own->admin_guru_id, 'jenis_guru' => 'guru_mapel']);
        $this->assertDatabaseHas('keanggotaan_sekolah', ['guru_id' => $own->admin_guru_id, 'sekolah_id' => $employer->id, 'jenis_guru' => 'guru_piket']);

        $this->actingAs($own->admin->user->fresh())->postJson('/api/v1/sekolah/pilih', ['sekolah_id' => $employer->id])->assertOk();
        $this->get('/data-sekolah')->assertInertia(fn (Assert $page) => $page
            ->where('auth.schoolTeacherType', 'guru_piket')->where('auth.isSchoolAdmin', false)->where('auth.canCreateClass', false));
        $this->getJson('/api/v1/admin-sekolah')->assertForbidden();
        $this->postJson('/api/v1/kelas-mapel', ['sekolah_id' => $own->id, 'nama_kelas_mapel' => 'Kelas pemilik'])->assertCreated();
        $this->postJson('/api/v1/kelas-mapel', ['sekolah_id' => $employer->id, 'nama_kelas_mapel' => 'Dilarang piket'])->assertForbidden();
    }

    public function test_owner_can_join_one_external_school_without_replacing_owned_memberships(): void
    {
        $own = Sekolah::factory()->withAdmin()->create();
        $otherOwned = Sekolah::factory()->withAdmin()->create(['admin_guru_id' => $own->admin_guru_id]);
        $external = Sekolah::factory()->create();
        $another = Sekolah::factory()->create();

        $this->actingAs($own->admin->user)->getJson('/api/v1/sekolah')->assertJsonPath('can_join_school', true);
        $this->postJson('/api/v1/sekolah/gabung', ['kode_sekolah' => $own->kode_sekolah])->assertUnprocessable();
        $this->postJson('/api/v1/sekolah/gabung', ['kode_sekolah' => $external->kode_sekolah])->assertCreated();
        $this->getJson('/api/v1/sekolah')->assertJsonCount(3, 'memberships')->assertJsonPath('can_join_school', false);
        $this->postJson('/api/v1/sekolah/gabung', ['kode_sekolah' => $another->kode_sekolah])->assertUnprocessable();
        $this->postJson('/api/v1/sekolah/pilih', ['sekolah_id' => $otherOwned->id])->assertOk();
        $this->deleteJson('/api/v1/sekolah/permintaan', ['sekolah_id' => $external->id])->assertOk();
        $this->assertDatabaseHas('keanggotaan_sekolah', ['guru_id' => $own->admin_guru_id, 'sekolah_id' => $otherOwned->id, 'status' => 'diterima']);
        $this->getJson('/api/v1/sekolah')->assertJsonPath('can_join_school', true);
        $this->postJson('/api/v1/sekolah/gabung', ['kode_sekolah' => $another->kode_sekolah])->assertCreated();
    }

    public function test_review_and_role_change_target_explicit_school_even_when_another_school_is_selected(): void
    {
        $first = Sekolah::factory()->withAdmin()->create();
        $second = Sekolah::factory()->withAdmin()->create(['admin_guru_id' => $first->admin_guru_id]);
        $member = KeanggotaanSekolah::factory()->create(['sekolah_id' => $first->id]);
        $this->actingAs($first->admin->user)->postJson('/api/v1/sekolah/pilih', ['sekolah_id' => $second->id])->assertOk();

        $this->patchJson('/api/v1/admin-sekolah/anggota/'.$member->id, ['status' => 'diterima'])->assertOk();
        $this->patchJson('/api/v1/admin-sekolah/guru/'.$member->guru_id, [
            'sekolah_id' => $first->id, 'jenis_guru' => 'guru_piket',
        ])->assertOk();
        $this->assertDatabaseHas('keanggotaan_sekolah', ['id' => $member->id, 'status' => 'diterima', 'jenis_guru' => 'guru_piket']);
        $this->getJson('/api/v1/admin-sekolah')->assertJsonPath('school.id', $second->id);
    }

    public function test_school_selection_and_cancellation_require_owned_membership_and_explicit_identifier(): void
    {
        $member = KeanggotaanSekolah::factory()->create();
        $foreign = KeanggotaanSekolah::factory()->create();
        $this->actingAs($member->guru->user)->postJson('/api/v1/sekolah/pilih', [])->assertUnprocessable();
        $this->deleteJson('/api/v1/sekolah/permintaan')->assertUnprocessable();
        $this->deleteJson('/api/v1/sekolah/permintaan', ['sekolah_id' => $foreign->sekolah_id])->assertNotFound();
        $this->postJson('/api/v1/sekolah/pilih', ['sekolah_id' => $member->sekolah_id])->assertOk();
        $this->getJson('/api/v1/admin-sekolah')->assertForbidden();
        $this->assertDatabaseHas('keanggotaan_sekolah', ['id' => $member->id, 'status' => 'pending']);
        $this->assertDatabaseHas('keanggotaan_sekolah', ['id' => $foreign->id, 'status' => 'pending']);
    }

    public function test_rollback_does_not_discard_school_specific_teacher_permissions(): void
    {
        $member = KeanggotaanSekolah::factory()->accepted()->create(['jenis_guru' => 'guru_piket']);
        $migration = require database_path('migrations/2026_09_28_055658_support_multiple_school_subscriptions.php');

        try {
            $migration->down();
            $this->fail('Rollback must refuse to discard scoped teacher permissions.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('perubahan jenis guru sekolah', $exception->getMessage());
        }

        $this->assertDatabaseHas('keanggotaan_sekolah', ['id' => $member->id, 'jenis_guru' => 'guru_piket']);
        $this->assertDatabaseHas('guru', ['id' => $member->guru_id, 'jenis_guru' => 'guru_mapel']);
    }
}
