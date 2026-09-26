<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_permissions_page_requires_authentication(): void
    {
        $this->get(route('permissions.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_user_cannot_access_permissions_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get(route('permissions.index'))->assertForbidden();
    }

    public function test_admin_can_access_permissions_page(): void
    {
        $this->actingAs($this->admin())->get(route('permissions.index'))->assertOk();
    }

    public function test_seeded_permissions_are_listed(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::permissions.index')
            ->assertSee('users.view')
            ->assertSee('roles.delete')
            ->assertSee('permissions.update');

        $this->assertSame(
            count(PermissionsSeeder::PERMISSIONS),
            Permission::count()
        );
    }

    public function test_admin_role_is_seeded_with_every_permission(): void
    {
        $admin = Role::findByName('admin');

        $this->assertEqualsCanonicalizing(
            Permission::pluck('name')->all(),
            $admin->permissions->pluck('name')->all()
        );
    }

    public function test_user_role_is_seeded_without_permissions(): void
    {
        $this->assertSame(0, Role::findByName('user')->permissions()->count());
    }

    public function test_can_create_permission(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::permissions.index')
            ->call('createPermission')
            ->assertSet('showPermissionModal', true)
            ->set('name', 'reports.export')
            ->call('savePermission')
            ->assertHasNoErrors()
            ->assertSet('showPermissionModal', false);

        $this->assertDatabaseHas('permissions', [
            'name' => 'reports.export',
            'guard_name' => 'web',
        ]);
    }

    public function test_cannot_create_duplicate_permission(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::permissions.index')
            ->call('createPermission')
            ->set('name', 'users.view')
            ->call('savePermission')
            ->assertHasErrors(['name']);
    }

    public function test_cannot_create_permission_with_invalid_name_format(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::permissions.index')
            ->call('createPermission')
            ->set('name', 'Users View')
            ->call('savePermission')
            ->assertHasErrors(['name']);

        $this->assertDatabaseMissing('permissions', ['name' => 'Users View']);
    }

    public function test_can_update_permission(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::permissions.index')
            ->call('editPermission', Permission::findByName('users.view')->id)
            ->assertSet('showPermissionModal', true)
            ->assertSet('name', 'users.view')
            ->set('name', 'users.read')
            ->call('savePermission')
            ->assertHasNoErrors()
            ->assertSet('showPermissionModal', false);

        $this->assertDatabaseHas('permissions', ['name' => 'users.read']);
        $this->assertDatabaseMissing('permissions', ['name' => 'users.view']);
    }

    public function test_cannot_delete_permission_assigned_to_role(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::permissions.index')
            ->call('confirmDelete', Permission::findByName('users.view')->id)
            ->assertSet('deletingPermissionRolesCount', 1)
            ->call('deletePermission')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseHas('permissions', ['name' => 'users.view']);
    }

    public function test_cannot_delete_permission_assigned_directly_to_user(): void
    {
        $this->actingAs($this->admin());

        $permission = Permission::findByName('users.view');
        Role::findByName('admin')->revokePermissionTo($permission);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        Livewire::test('pages::permissions.index')
            ->call('confirmDelete', $permission->id)
            ->assertSet('deletingPermissionRolesCount', 0)
            ->assertSet('deletingPermissionUsersCount', 1)
            ->call('deletePermission')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseHas('permissions', ['name' => 'users.view']);
    }

    public function test_can_delete_unused_permission(): void
    {
        $this->actingAs($this->admin());

        $permission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);

        Livewire::test('pages::permissions.index')
            ->call('confirmDelete', $permission->id)
            ->assertSet('deletingPermissionRolesCount', 0)
            ->assertSet('deletingPermissionUsersCount', 0)
            ->call('deletePermission')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseMissing('permissions', ['name' => 'reports.export']);
    }

    public function test_cannot_sort_by_non_whitelisted_field(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::permissions.index')
            ->assertSet('sortField', 'name')
            ->call('sortBy', 'id; drop table permissions')
            ->assertSet('sortField', 'name')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'guard_name')
            ->assertSet('sortField', 'guard_name');
    }
}
