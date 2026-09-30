<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpatiePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_grant_permissions_without_changing_the_existing_account_role(): void
    {
        $user = User::factory()->create(['role' => 'guru']);
        $role = Role::create(['name' => 'reviewer', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'review reports', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->assertFalse($user->can('review reports'));

        $user->assignRole($role);

        $this->assertTrue($user->fresh()->hasRole('reviewer'));
        $this->assertTrue($user->fresh()->can('review reports'));
        $this->assertSame('guru', $user->fresh()->role);

        $user->removeRole($role);

        $this->assertFalse($user->fresh()->can('review reports'));
    }

    #[TestWith(['role:reviewer'])]
    #[TestWith(['permission:review reports'])]
    #[TestWith(['role_or_permission:reviewer|review reports'])]
    public function test_middleware_rejects_guests_and_unassigned_users_and_allows_authorized_users(string $middleware): void
    {
        Route::get('/permission-test', fn () => response()->noContent())->middleware($middleware);

        $role = Role::create(['name' => 'reviewer', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'review reports', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user = User::factory()->create();

        $this->getJson('/permission-test')->assertForbidden();
        $this->actingAs($user)->getJson('/permission-test')->assertForbidden();

        $user->assignRole($role);

        $this->actingAs($user->fresh())->getJson('/permission-test')->assertNoContent();
    }

    public function test_direct_permissions_can_be_granted_and_revoked(): void
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'review reports', 'guard_name' => 'web']);

        $user->givePermissionTo($permission);
        $this->assertTrue($user->fresh()->can('review reports'));

        $user->revokePermissionTo($permission);
        $this->assertFalse($user->fresh()->can('review reports'));
    }
}
