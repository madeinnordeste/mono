<?php

namespace Tests\Feature\Roles;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_roles_page_requires_authentication(): void
    {
        $this->get(route('roles.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_user_cannot_access_roles_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get(route('roles.index'))->assertForbidden();
    }

    public function test_admin_can_access_roles_page(): void
    {
        $this->actingAs($this->admin())->get(route('roles.index'))->assertOk();
    }

    public function test_can_list_and_search_roles(): void
    {
        $this->actingAs($this->admin());

        Role::create(['name' => 'auditor', 'guard_name' => 'web']);

        Livewire::test('pages::roles.index')
            ->assertSee('auditor')
            ->assertSee('admin')
            ->set('search', 'audit')
            ->assertSee('auditor')
            ->set('search', 'no-such-role')
            ->assertDontSee('auditor')
            ->assertDontSee('admin');
    }

    public function test_can_create_role(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::roles.index')
            ->call('createRole')
            ->assertSet('showRoleModal', true)
            ->set('name', 'editor')
            ->call('saveRole')
            ->assertHasNoErrors()
            ->assertSet('showRoleModal', false);

        $this->assertDatabaseHas('roles', [
            'name' => 'editor',
            'guard_name' => 'web',
        ]);
    }

    public function test_cannot_create_duplicate_role_name(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::roles.index')
            ->call('createRole')
            ->set('name', 'admin')
            ->call('saveRole')
            ->assertHasErrors(['name']);

        $this->assertDatabaseCount('roles', 2);
    }

    public function test_cannot_create_role_with_invalid_name_format(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::roles.index')
            ->call('createRole')
            ->set('name', 'Invalid Role!')
            ->call('saveRole')
            ->assertHasErrors(['name']);

        $this->assertDatabaseMissing('roles', ['name' => 'Invalid Role!']);
    }

    public function test_can_update_role(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::roles.index')
            ->call('editRole', Role::findByName('user')->id)
            ->assertSet('showRoleModal', true)
            ->assertSet('name', 'user')
            ->set('name', 'member')
            ->call('saveRole')
            ->assertHasNoErrors()
            ->assertSet('showRoleModal', false);

        $this->assertDatabaseHas('roles', ['name' => 'member']);
        $this->assertDatabaseMissing('roles', ['name' => 'user']);
    }

    public function test_cannot_rename_admin_role(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::roles.index')
            ->call('editRole', Role::findByName('admin')->id)
            ->set('name', 'superuser')
            ->call('saveRole')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
        $this->assertDatabaseMissing('roles', ['name' => 'superuser']);
    }

    public function test_cannot_delete_admin_role(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::roles.index')
            ->call('confirmDelete', Role::findByName('admin')->id)
            ->assertSet('showDeleteModal', true)
            ->call('deleteRole')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
    }

    public function test_cannot_delete_role_with_assigned_users(): void
    {
        $this->actingAs($this->admin());

        $member = User::factory()->create();
        $member->assignRole('user');

        Livewire::test('pages::roles.index')
            ->call('confirmDelete', Role::findByName('user')->id)
            ->assertSet('deletingRoleUsersCount', 1)
            ->call('deleteRole')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseHas('roles', ['name' => 'user']);
    }

    public function test_can_delete_unused_role(): void
    {
        $this->actingAs($this->admin());

        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        Livewire::test('pages::roles.index')
            ->call('confirmDelete', $role->id)
            ->assertSet('deletingRoleUsersCount', 0)
            ->call('deleteRole')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);
    }

    public function test_can_sync_permissions_to_role(): void
    {
        $this->actingAs($this->admin());

        $role = Role::findByName('user');

        Livewire::test('pages::roles.index')
            ->call('managePermissions', $role->id)
            ->assertSet('showPermissionsModal', true)
            ->assertSet('managingRoleName', 'user')
            ->assertSet('selectedPermissions', [])
            ->set('selectedPermissions', ['users.view', 'users.update'])
            ->call('syncPermissions')
            ->assertHasNoErrors()
            ->assertSet('showPermissionsModal', false);

        $this->assertDatabaseHas('role_has_permissions', [
            'permission_id' => Permission::findByName('users.view')->id,
            'role_id' => $role->id,
        ]);
        $this->assertDatabaseHas('role_has_permissions', [
            'permission_id' => Permission::findByName('users.update')->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_cannot_sync_unknown_permission_to_role(): void
    {
        $this->actingAs($this->admin());

        $role = Role::findByName('user');

        Livewire::test('pages::roles.index')
            ->call('managePermissions', $role->id)
            ->set('selectedPermissions', ['superuser.manage'])
            ->call('syncPermissions')
            ->assertHasErrors(['selectedPermissions.0']);

        $this->assertSame(0, $role->permissions()->count());
    }

    public function test_can_remove_all_permissions_from_role(): void
    {
        $this->actingAs($this->admin());

        $role = Role::findByName('admin');

        Livewire::test('pages::roles.index')
            ->call('managePermissions', $role->id)
            ->assertCount('selectedPermissions', Permission::count())
            ->set('selectedPermissions', [])
            ->call('syncPermissions')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('role_has_permissions', 0);
    }

    public function test_cannot_sort_by_non_whitelisted_field(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::roles.index')
            ->assertSet('sortField', 'name')
            ->call('sortBy', 'guard_name; drop table roles')
            ->assertSet('sortField', 'name')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'created_at')
            ->assertSet('sortField', 'created_at');
    }
}
