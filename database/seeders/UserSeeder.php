<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $staff = Role::create(['name' => 'staff', 'guard_name' => 'api']);

        $user = User::factory()->create([
            'first_name'    => 'Admin',
            'last_name'     => 'Staffmanager',
            'email'         => 'admin@email.com',
            'phone_number'  => '+639957433413'
        ]);

        $user->assignRole($admin);
    }
}
