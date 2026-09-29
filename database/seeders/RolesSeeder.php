<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Seed the application's roles.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user'], ['guard_name' => 'web']);
    }
}
