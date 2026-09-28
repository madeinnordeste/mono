<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
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

        $this->actingAs($currentUser);

        Livewire::test('pages::users.index')
            ->call('confirmDelete', $currentUser->id)
            ->call('deleteUser');

        $this->assertDatabaseHas('users', [
            'id' => $currentUser->id,
        ]);
    }
}
