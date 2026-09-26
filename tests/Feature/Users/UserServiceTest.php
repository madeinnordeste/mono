<?php

namespace Tests\Feature\Users;

use App\Exceptions\LastAdminException;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginates_users(): void
    {
        User::factory()->count(11)->create();

        $users = $this->service()->paginate('', 'name', 'asc');

        $this->assertSame(10, $users->count());
        $this->assertSame(11, $users->total());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function searchTerms(): array
    {
        return [
            'name' => ['John', 'john@example.com'],
            'email' => ['jane@example.com', 'jane@example.com'],
        ];
    }

    #[DataProvider('searchTerms')]
    public function test_filters_users_by_name_or_email(string $search, string $expectedEmail): void
    {
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $users = $this->service()->paginate($search, 'name', 'asc');

        $this->assertSame(1, $users->total());
        $this->assertSame($expectedEmail, $users->first()->email);
    }

    public function test_falls_back_to_safe_sort_field_and_direction(): void
    {
        User::factory()->create(['name' => 'Zulu']);
        User::factory()->create(['name' => 'Alpha']);

        $users = $this->service()->paginate('', 'password; drop table users', 'sideways');

        $this->assertSame('Alpha', $users->first()->name);
    }

    public function test_creates_user_with_default_role_when_no_roles_are_selected(): void
    {
        $user = $this->service()->create([
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ]);

        $this->assertModelExists($user);
        $this->assertTrue($user->hasRole('user'));
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_creates_user_with_selected_roles(): void
    {
        $user = $this->service()->create([
            'name' => 'Admin User',
            'email' => 'admin-user@example.com',
            'password' => 'password123',
            'selectedRoles' => ['admin'],
        ]);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('user'));
    }

    public function test_updates_user_without_replacing_password_when_blank(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->service()->update($user->id, [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'password' => '',
            'selectedRoles' => [],
        ]);

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertTrue(Hash::check('old-password', $user->password));
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_updates_user_password_when_provided(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->service()->update($user->id, [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'password' => 'new-secure-password',
            'selectedRoles' => [],
        ]);

        $this->assertTrue(Hash::check('new-secure-password', $user->refresh()->password));
    }

    public function test_prevents_removing_admin_role_from_last_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        try {
            $this->service()->update($admin->id, [
                'name' => $admin->name,
                'email' => $admin->email,
                'password' => '',
                'selectedRoles' => ['user'],
            ]);

            $this->fail('Expected LastAdminException to be thrown.');
        } catch (LastAdminException $exception) {
            $this->assertSame('At least one user must keep the admin role.', $exception->getMessage());
        }

        $this->assertTrue($admin->refresh()->hasRole('admin'));
    }

    public function test_deletes_user(): void
    {
        $user = User::factory()->create();

        $this->service()->delete($user->id);

        $this->assertModelMissing($user);
    }

    public function test_prevents_deleting_last_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        try {
            $this->service()->delete($admin->id);

            $this->fail('Expected LastAdminException to be thrown.');
        } catch (LastAdminException $exception) {
            $this->assertSame('You cannot delete the last administrator.', $exception->getMessage());
        }

        $this->assertModelExists($admin);
    }

    public function test_returns_only_web_roles_sorted_by_name(): void
    {
        Role::create(['name' => 'zeta', 'guard_name' => 'web']);
        Role::create(['name' => 'alpha', 'guard_name' => 'web']);
        Role::create(['name' => 'api-role', 'guard_name' => 'api']);

        $roles = $this->service()->availableRoles();

        $this->assertSame(['admin', 'alpha', 'user', 'zeta'], $roles->pluck('name')->all());
    }

    private function service(): UserService
    {
        return app(UserService::class);
    }
}
