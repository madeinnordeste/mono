<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_horizon_route(): void
    {
        $this->get('/horizon')->assertRedirect('/login');
    }

    public function test_user_role_cannot_access_horizon_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/horizon')->assertForbidden();
    }

    public function test_admin_role_can_access_horizon_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get('/horizon')->assertOk();
    }
}
