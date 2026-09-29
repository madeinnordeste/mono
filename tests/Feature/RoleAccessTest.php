<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_users_route(): void
    {
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_user_role_cannot_access_users_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_admin_role_can_access_users_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get('/users')->assertOk();
    }

    public function test_user_role_cannot_access_roles_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/roles')->assertForbidden();
    }

    public function test_user_role_cannot_access_permissions_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/permissions')->assertForbidden();
    }

    public function test_admin_role_can_access_roles_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get('/roles')->assertOk();
    }

    public function test_admin_role_can_access_permissions_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get('/permissions')->assertOk();
    }
}
