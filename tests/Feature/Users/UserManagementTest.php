<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_page_requires_authentication(): void
    {
        $response = $this->get(route('users.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_users_page_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get(route('users.index'));

        $response->assertOk();
    }

    public function test_user_initials_handles_null_or_empty_name(): void
    {
        $user = new User;
        $this->assertSame('', $user->initials());

        $user->name = '';
        $this->assertSame('', $user->initials());

        $user->name = 'John';
        $this->assertSame('J', $user->initials());

        $user->name = 'John Doe';
        $this->assertSame('JD', $user->initials());
    }

    public function test_can_list_and_search_users(): void
    {
        $currentUser = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@example.com']);
        $currentUser->assignRole('admin');
        $john = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $jane = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->assertSee('John Doe')
            ->assertSee('Jane Smith')
            ->set('search', 'John')
            ->assertSee('John Doe')
            ->assertDontSee('Jane Smith');
    }

    public function test_can_sort_users(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->assertSet('sortField', 'name')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'name')
            ->assertSet('sortDirection', 'desc')
            ->call('sortBy', 'email')
            ->assertSet('sortField', 'email')
            ->assertSet('sortDirection', 'asc');
    }

    public function test_can_create_user(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('createUser')
            ->assertSet('showUserModal', true)
            ->set('name', 'New User')
            ->set('email', 'newuser@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('saveUser')
            ->assertHasNoErrors()
            ->assertSet('showUserModal', false);

        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ]);
    }

    public function test_cannot_create_user_with_duplicate_email(): void
    {
        $currentUser = User::factory()->create(['email' => 'existing@example.com']);
        $currentUser->assignRole('admin');
        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('createUser')
            ->set('name', 'Another User')
            ->set('email', 'existing@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('saveUser')
            ->assertHasErrors(['email']);
    }

    public function test_can_update_user(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('editUser', $user->id)
            ->assertSet('showUserModal', true)
            ->assertSet('name', 'Old Name')
            ->assertSet('email', 'old@example.com')
            ->set('name', 'Updated Name')
            ->set('email', 'updated@example.com')
            ->call('saveUser')
            ->assertHasNoErrors()
            ->assertSet('showUserModal', false);

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
    }

    public function test_can_update_user_password_optionally(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('editUser', $user->id)
            ->set('name', 'Same Name')
            ->set('email', $user->email)
            ->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password')
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-secure-password', $user->refresh()->password));
    }

    public function test_can_delete_user(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        $user = User::factory()->create();

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('confirmDelete', $user->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingUserId', $user->id)
            ->call('deleteUser')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_cannot_delete_own_account_via_users_crud(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('confirmDelete', $currentUser->id)
            ->call('deleteUser');

        $this->assertDatabaseHas('users', [
            'id' => $currentUser->id,
        ]);
    }

    public function test_created_user_receives_default_role(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('createUser')
            ->set('name', 'New User')
            ->set('email', 'newuser@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('saveUser')
            ->assertHasNoErrors();

        $user = User::where('email', 'newuser@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('user'));
    }

    public function test_can_assign_role_to_user(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('editUser', $user->id)
            ->assertSet('selectedRoles', ['user'])
            ->set('selectedRoles', ['admin'])
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertTrue($user->refresh()->hasRole('admin'));
        $this->assertFalse($user->hasRole('user'));
    }

    public function test_can_sync_multiple_roles_on_user(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('editUser', $user->id)
            ->set('selectedRoles', ['user', 'admin'])
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['admin', 'user'],
            $user->refresh()->roles->pluck('name')->all()
        );
    }

    public function test_cannot_assign_unknown_role(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('editUser', $user->id)
            ->set('selectedRoles', ['superuser'])
            ->call('saveUser')
            ->assertHasErrors(['selectedRoles.0' => 'exists']);

        $this->assertTrue($user->refresh()->hasRole('user'));
    }

    public function test_cannot_remove_admin_from_last_admin(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('editUser', $currentUser->id)
            ->set('selectedRoles', ['user'])
            ->call('saveUser')
            ->assertHasErrors(['selectedRoles']);

        $this->assertTrue($currentUser->refresh()->hasRole('admin'));
    }

    public function test_can_remove_admin_from_admin_when_another_one_remains(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('editUser', $user->id)
            ->set('selectedRoles', ['user'])
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertFalse($user->refresh()->hasRole('admin'));
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_cannot_delete_last_admin_user(): void
    {
        $lastAdmin = User::factory()->create();
        $lastAdmin->assignRole('admin');

        $acting = User::factory()->create();
        $acting->assignRole('user');

        $this->actingAs($acting);

        Livewire::test('pages::users.index')
            ->call('confirmDelete', $lastAdmin->id)
            ->call('deleteUser');

        $this->assertDatabaseHas('users', ['id' => $lastAdmin->id]);
    }

    public function test_users_table_lists_assigned_roles(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        Role::create(['name' => 'auditor', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Jane Smith']);
        $user->assignRole('auditor');

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->assertSeeText('Jane Smith')
            ->assertSeeText('auditor')
            ->assertSeeText('admin');
    }

    public function test_roles_selector_is_a_checkbox_group_bound_to_selected_roles(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        Role::create(['name' => 'auditor', 'guard_name' => 'web']);

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('createUser')
            ->assertSeeHtml('wire:model.self="selectedRoles"')
            ->assertSeeHtml('data-flux-checkbox-group')
            ->assertSeeHtml('value="auditor"')
            ->assertSeeHtml('value="user"');
    }

    public function test_editing_a_user_marks_the_roles_they_already_have(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        Role::create(['name' => 'auditor', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('auditor');

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('editUser', $user->id)
            ->assertSet('selectedRoles', ['auditor'])
            ->assertSeeHtml('value="auditor"');
    }

    public function test_cannot_sort_users_by_non_whitelisted_field(): void
    {
        $currentUser = User::factory()->create();
        $currentUser->assignRole('admin');
        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->assertSet('sortField', 'name')
            ->call('sortBy', 'password; drop table users')
            ->assertSet('sortField', 'name')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'created_at')
            ->assertSet('sortField', 'created_at');
    }
}
