<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Seed the application's roles and their permissions.
     */
    public function run(): void
    {
        $this->call(PermissionsSeeder::class);

        $admin = Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'user'], ['guard_name' => 'web']);

        $admin->syncPermissions(Permission::all());
    }
}
