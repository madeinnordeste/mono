<?php

namespace Tests\Feature\Roles;

use App\Exceptions\RoleDeletionException;
use App\Models\User;
use App\Services\Roles\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginates_roles(): void
    {
        foreach (range(1, 11) as $index) {
            Role::create(['name' => 'role-'.$index, 'guard_name' => 'web']);
        }

        $roles = $this->service()->paginate('', 'name', 'asc');

        $this->assertSame(10, $roles->count());
        $this->assertSame(13, $roles->total());
    }

    public function test_filters_roles_by_name(): void
    {
        Role::create(['name' => 'auditor', 'guard_name' => 'web']);

        $roles = $this->service()->paginate('audit', 'name', 'asc');

        $this->assertSame(1, $roles->total());
        $this->assertSame('auditor', $roles->first()?->name);
    }

    public function test_sorts_roles_by_allowed_field_and_direction(): void
    {
        Role::create(['name' => 'zeta', 'guard_name' => 'web']);

        $roles = $this->service()->paginate('', 'name', 'desc');

        $this->assertSame('zeta', $roles->first()?->name);
    }

    public function test_falls_back_to_safe_sort_field_and_direction(): void
    {
        $roles = $this->service()->paginate('', 'name; drop table roles', 'sideways');

        $this->assertSame('admin', $roles->first()?->name);
    }

    public function test_creates_role_with_web_guard(): void
    {
        $role = $this->service()->create(['name' => 'editor']);

        $this->assertModelExists($role);
        $this->assertSame('editor', $role->name);
        $this->assertSame('web', $role->guard_name);
    }

    public function test_updates_role(): void
    {
        $role = Role::findByName('user');

        $this->service()->update($role->id, ['name' => 'member']);

        $this->assertSame('member', $role->refresh()->name);
    }

    public function test_updating_admin_role_does_not_change_its_name(): void
    {
        $admin = Role::findByName('admin');

        $this->service()->update($admin->id, ['name' => 'superuser']);

        $this->assertSame('admin', $admin->refresh()->name);
        $this->assertDatabaseMissing('roles', ['name' => 'superuser']);
    }

    public function test_syncs_permissions_to_role(): void
    {
        $role = Role::findByName('user');

        $this->service()->syncPermissions($role->id, ['users.view', 'users.update']);

        $this->assertEqualsCanonicalizing(
            ['users.view', 'users.update'],
            $role->permissions()->pluck('name')->all(),
        );
    }

    public function test_syncs_empty_permission_list_to_role(): void
    {
        $admin = Role::findByName('admin');

        $this->assertGreaterThan(0, $admin->permissions()->count());

        $this->service()->syncPermissions($admin->id, []);

        $this->assertSame(0, $admin->permissions()->count());
        $this->assertDatabaseCount('role_has_permissions', 0);
    }

    public function test_returns_role_with_its_permissions(): void
    {
        $admin = Role::findByName('admin');

        $role = $this->service()->findWithPermissions($admin->id);

        $this->assertTrue($role->relationLoaded('permissions'));
        $this->assertEqualsCanonicalizing(
            Permission::pluck('name')->all(),
            $role->permissions->pluck('name')->all(),
        );
    }

    public function test_returns_role_with_user_count(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $role = $this->service()->findWithUserCount(Role::findByName('user')->id);

        $this->assertSame(1, $role->users_count);
    }

    public function test_identifies_protected_role(): void
    {
        $this->assertTrue($this->service()->isProtected(Role::findByName('admin')->id));
        $this->assertFalse($this->service()->isProtected(Role::findByName('user')->id));
        $this->assertFalse($this->service()->isProtected(null));
    }

    public function test_deletes_unused_role(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->service()->delete($role->id);

        $this->assertModelMissing($role);
    }

    public function test_prevents_deleting_admin_role(): void
    {
        $admin = Role::findByName('admin');

        try {
            $this->service()->delete($admin->id);

            $this->fail('Expected RoleDeletionException to be thrown.');
        } catch (RoleDeletionException $exception) {
            $this->assertSame('The admin role cannot be deleted.', $exception->getMessage());
        }

        $this->assertModelExists($admin);
    }

    public function test_prevents_deleting_role_with_assigned_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $role = Role::findByName('user');

        try {
            $this->service()->delete($role->id);

            $this->fail('Expected RoleDeletionException to be thrown.');
        } catch (RoleDeletionException $exception) {
            $this->assertSame('This role has users assigned to it and cannot be deleted.', $exception->getMessage());
        }

        $this->assertModelExists($role);
    }

    private function service(): RoleService
    {
        return app(RoleService::class);
    }
}
