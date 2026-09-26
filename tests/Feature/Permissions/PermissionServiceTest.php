<?php

namespace Tests\Feature\Permissions;

use App\Exceptions\PermissionDeletionException;
use App\Models\User;
use App\Services\Permissions\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginates_permissions(): void
    {
        $permissions = $this->service()->paginate('', 'name', 'asc');

        $this->assertSame(10, $permissions->count());
        $this->assertSame(13, $permissions->total());
    }

    public function test_filters_permissions_by_name(): void
    {
        Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);

        $permissions = $this->service()->paginate('reports', 'name', 'asc');

        $this->assertSame(1, $permissions->total());
        $this->assertSame('reports.export', $permissions->first()?->name);
    }

    public function test_sorts_permissions_by_allowed_field_and_direction(): void
    {
        Permission::create(['name' => 'zeta.view', 'guard_name' => 'web']);

        $permissions = $this->service()->paginate('', 'name', 'desc');

        $this->assertSame('zeta.view', $permissions->first()?->name);
    }

    public function test_falls_back_to_safe_sort_field_and_direction(): void
    {
        $permissions = $this->service()->paginate('', 'name; drop table permissions', 'sideways');

        $this->assertSame('permissions.create', $permissions->first()?->name);
    }

    public function test_creates_permission_with_web_guard(): void
    {
        $permission = $this->service()->create(['name' => 'reports.export']);

        $this->assertModelExists($permission);
        $this->assertSame('reports.export', $permission->name);
        $this->assertSame('web', $permission->guard_name);
    }

    public function test_updates_permission(): void
    {
        $permission = Permission::findByName('users.view');

        $this->service()->update($permission->id, ['name' => 'users.read']);

        $this->assertSame('users.read', $permission->refresh()->name);
    }

    public function test_groups_web_permissions_by_resource_prefix(): void
    {
        $groups = $this->service()->groupedNames();

        $this->assertSame(['permissions', 'roles', 'users'], array_keys($groups->all()));
        $this->assertSame(
            ['roles.assign', 'roles.create', 'roles.delete', 'roles.update', 'roles.view'],
            $groups->get('roles')?->all(),
        );
    }

    public function test_groups_permissions_for_a_specific_guard(): void
    {
        Permission::create(['name' => 'api.view', 'guard_name' => 'api']);

        $this->assertFalse($this->service()->groupedNames()->has('api'));
        $this->assertSame(['api'], array_keys($this->service()->groupedNames('api')->all()));
    }

    public function test_paginates_permissions_with_relationship_counts(): void
    {
        $permissions = $this->service()->paginate('users.view', 'name', 'asc');

        $permission = $permissions->first();

        $this->assertSame(1, $permission?->roles_count);
        $this->assertSame(0, $permission?->users_count);
    }

    public function test_returns_permission_with_relationship_counts(): void
    {
        $permission = $this->service()->findWithCounts(Permission::findByName('users.view')->id);

        $this->assertSame(1, $permission->roles_count);
        $this->assertSame(0, $permission->users_count);
    }

    public function test_deletes_unused_permission(): void
    {
        $permission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);

        $this->service()->delete($permission->id);

        $this->assertModelMissing($permission);
    }

    public function test_prevents_deleting_permission_assigned_to_role(): void
    {
        $permission = Permission::findByName('users.view');

        try {
            $this->service()->delete($permission->id);

            $this->fail('Expected PermissionDeletionException to be thrown.');
        } catch (PermissionDeletionException $exception) {
            $this->assertSame('This permission is granted to roles and cannot be deleted.', $exception->getMessage());
        }

        $this->assertModelExists($permission);
    }

    public function test_prevents_deleting_permission_assigned_directly_to_user(): void
    {
        $permission = Permission::findByName('users.view');
        Role::findByName('admin')->revokePermissionTo($permission);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        try {
            $this->service()->delete($permission->id);

            $this->fail('Expected PermissionDeletionException to be thrown.');
        } catch (PermissionDeletionException $exception) {
            $this->assertSame('This permission is granted directly to users and cannot be deleted.', $exception->getMessage());
        }

        $this->assertModelExists($permission);
    }

    private function service(): PermissionService
    {
        return app(PermissionService::class);
    }
}
