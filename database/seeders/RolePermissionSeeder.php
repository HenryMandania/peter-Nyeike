<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1️⃣ Define Roles
        $webRoles = ['admin', 'supervisor', 'field-operator'];
        foreach ($webRoles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
        
        // Define an API-specific role for Sanctum users
        $apiRole = Role::firstOrCreate(['name' => 'api-user', 'guard_name' => 'sanctum']);

        // 2️⃣ Define Resources & Actions
        $resources = [
            'supplier', 'companypayment', 'company', 'expensecategory', 
            'expense', 'floatrequest', 'item', 'mpesatransaction', 
            'purchase', 'sale', 'shift'
        ];
        $actions = ['view', 'create', 'edit', 'delete'];

        // Create Web Permissions (Filament)
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "$resource.$action", 'guard_name' => 'web']);
            }
        }
        
        // Create Custom Business Web Permissions
        $customPerms = ['purchase.approve', 'purchase.pay', 'purchase.sell', 'purchase.reject', 'floatrequest.approve', 'floatrequest.reject'];
        foreach ($customPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 3️⃣ Create Sanctum Permissions (API Only)
        // Only include what the mobile/API app specifically needs
        $apiPermissions = [
            'purchase.view', 'purchase.create', 'purchase.approve', 
            'floatrequest.view', 'floatrequest.create', 'floatrequest.approve', 'floatrequest.reject'
        ];
        foreach ($apiPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }

        // 4️⃣ Assign Web Permissions
        Role::findByName('admin', 'web')->syncPermissions(Permission::where('guard_name', 'web')->get());
        
        Role::findByName('supervisor', 'web')->syncPermissions(Permission::where('name', 'like', '%.view')->where('guard_name', 'web')->get());

        Role::findByName('field-operator', 'web')->syncPermissions([
            'expense.create', 'expense.view', 'expensecategory.create', 'expensecategory.edit',
            'floatrequest.view', 'floatrequest.create', 'item.create', 'item.view',
            'purchase.create', 'purchase.view', 'shift.create', 'shift.view', 'supplier.create', 'supplier.view'
        ]);

        // Assign Permissions to API role
        $apiRole->syncPermissions($apiPermissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}