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
        $roles = ['admin', 'supervisor', 'field-operator'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // 2️⃣ Define Resources & Actions
        $resources = [
            'supplier', 'companypayment', 'company', 'expensecategory', 
            'expense', 'floatrequest', 'item', 'mpesatransaction', 
            'purchase', 'sale', 'shift'
        ];
        $actions = ['view', 'create', 'edit', 'delete'];

        // Create standard CRUD permissions
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "$resource.$action", 'guard_name' => 'web']);
            }
        }

        // 3️⃣ Custom Business Permissions
        $customPerms = [
            'purchase.approve', 'purchase.pay', 'purchase.sell', 'purchase.reject',
            'floatrequest.approve', 'floatrequest.reject'
        ];
        foreach ($customPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 4️⃣ Assign Permissions
        
        // Admin: Explicitly get all 'web' guard permissions
        $admin = Role::findByName('admin', 'web');
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());

        // Supervisor: Gets all 'view' permissions
        $supervisor = Role::findByName('supervisor', 'web');
        $supervisor->syncPermissions(Permission::where('name', 'like', '%.view')
            ->where('guard_name', 'web')->get());

        // Field Operator: Specified list
        $fieldOperator = Role::findByName('field-operator', 'web');
        $fieldOperatorPermissions = [
            'expense.create', 'expense.view', 
            'expensecategory.create', 'expensecategory.edit',
            'floatrequest.view', 'floatrequest.create', 
            'item.create', 'item.view',
            'purchase.create', 'purchase.view', 
            'shift.create', 'shift.view',
            'supplier.create', 'supplier.view'
        ];
        $fieldOperator->syncPermissions($fieldOperatorPermissions);

        // Final cache clear
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}