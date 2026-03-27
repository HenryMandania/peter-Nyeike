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
        // Added 'float-request' variants here to satisfy the Field Operator sync
        $customPerms = [
            'dashboard.view', 
            'purchase.approve', 
            'purchase.pay', 
            'purchase.sell', 
            'purchase.reject', 
            'floatrequest.approve', 
            'floatrequest.reject',
            'float-request.view',   // Added to match your operator list
            'float-request.create', // Added to match your operator list
        ];

        foreach ($customPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 3️⃣ Create Sanctum Permissions (API Only)
        $apiPermissions = [
            'purchase.view', 'purchase.create', 'purchase.approve', 
            'float-request.view', 'float-request.create', 'float-request.approve', 'float-request.reject',
            'dashboard.view'
        ];
        foreach ($apiPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }

        // 4️⃣ Assign Web Permissions
        
        // Admin: Everything on web guard
        Role::findByName('admin', 'web')->syncPermissions(Permission::where('guard_name', 'web')->get());
        
        // Supervisor: All View permissions + Specific Actions
        Role::findByName('supervisor', 'web')->syncPermissions(
            Permission::where('guard_name', 'web')
                ->where(function($query) {
                    $query->where('name', 'like', '%.view')
                          ->orWhereIn('name', ['purchase.approve', 'floatrequest.approve', 'floatrequest.reject']);
                })->get()
        );

        // Field Operator: Corrected to use the created permission names
        Role::findByName('field-operator', 'web')->syncPermissions([
            'expense.create', 'expense.view', 'expensecategory.view',
            'float-request.view', 'float-request.create', 'item.create', 'item.view',
            'purchase.create', 'purchase.view', 'shift.create', 'shift.view', 
            'supplier.create', 'supplier.view', 'dashboard.view'
        ]);

        // Assign Permissions to API role (ensure they are the sanctum ones)
        $apiRole->syncPermissions(
            Permission::where('guard_name', 'sanctum')->whereIn('name', $apiPermissions)->get()
        );

        // Clean up cache again
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}