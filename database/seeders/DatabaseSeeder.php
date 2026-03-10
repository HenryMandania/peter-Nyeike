<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clear the Spatie cache to prevent old guard issues
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Define your exact dot-notation permissions
        $permissions = [
            'dashboard.view',            
        ];

        $guards = ['web', 'sanctum'];

        // 3. Create Roles and Permissions for both guards
        foreach ($guards as $guardName) {
            $adminRole = Role::firstOrCreate([
                'name' => 'admin', 
                'guard_name' => $guardName
            ]);

            foreach ($permissions as $permissionName) {
                Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => $guardName
                ]);
            }

            // Sync all defined permissions to the role for this specific guard
            $adminRole->syncPermissions($permissions);
        }

        // 4. Create or Update the Admin User
        $user = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('Tusker01'),
                'email_verified_at' => now(),
            ]
        );

        // 5. Assign Roles using sync on the relationship
        // This bypasses the assignRole() check that causes the GuardDoesNotMatch error
        $roleIds = Role::where('name', 'admin')->pluck('id')->toArray();
        $user->roles()->sync($roleIds);
    }
}