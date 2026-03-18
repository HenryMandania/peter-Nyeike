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
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1️⃣ Define Roles
        $webRoles = ['admin', 'supervisor', 'field-operator'];
        foreach ($webRoles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
        
        $apiRole = Role::firstOrCreate(['name' => 'api-user', 'guard_name' => 'sanctum']);

        // 2️⃣ Define Resources & Actions
        // Unified 'expensecategory' to match your list's 'expense-category' logic if needed, 
        // but kept as 'expensecategory' for database consistency.
        $resources = [
            'supplier', 'companypayment', 'company', 'expensecategory', 
            'expense', 'floatrequest', 'item', 'mpesatransaction', 
            'purchase', 'sale', 'shift'
        ];
        $actions = ['view', 'create', 'edit', 'delete'];

        // Create Standard CRUD Web Permissions
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "$resource.$action", 'guard_name' => 'web']);
            }
        }
        
        // Create Custom Business & Specific Web Permissions
        $customPerms = [
            'dashboard.view', 
            'purchase.approve', 
            'purchase.pay', 
            'purchase.sell', 
            'purchase.reject', 
            'floatrequest.approve', 
            'floatrequest.reject'
        ];

        foreach ($customPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 3️⃣ Create Sanctum Permissions (API Only)
        $apiPermissions = [
            'purchase.view', 'purchase.create', 'purchase.approve', 
            'floatrequest.view', 'floatrequest.create', 'floatrequest.approve', 'floatrequest.reject',
            'dashboard.view'
        ];
        foreach ($apiPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }

        // 4️⃣ Assign Web Permissions
        
        // Admin: Everything
        Role::findByName('admin', 'web')->syncPermissions(Permission::where('guard_name', 'web')->get());
        
        // Supervisor: All View permissions + Specific Actions
        Role::findByName('supervisor', 'web')->syncPermissions(
            Permission::where('guard_name', 'web')
                ->where(function($query) {
                    $query->where('name', 'like', '%.view')
                          ->orWhereIn('name', ['purchase.approve', 'floatrequest.approve']);
                })->get()
        );

        // Field Operator: Limited Create/View access
        Role::findByName('field-operator', 'web')->syncPermissions([
            'expense.create', 'expense.view', 'expensecategory.view',
            'floatrequest.view', 'floatrequest.create', 'item.create', 'item.view',
            'purchase.create', 'purchase.view', 'shift.create', 'shift.view', 
            'supplier.create', 'supplier.view', 'dashboard.view'
        ]);

        // Assign Permissions to API role
        $apiRole->syncPermissions($apiPermissions);

        // Clean up cache again
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}