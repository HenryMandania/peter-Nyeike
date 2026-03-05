<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role; // Add this

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create or find the admin role
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // 2. Create the user
        $user = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('Tusker01'),
                'email_verified_at' => now(),
            ]
        );

        // 3. Assign the role to the user
        $user->assignRole($adminRole);
    }
}