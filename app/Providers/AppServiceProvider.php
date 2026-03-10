<?php

namespace App\Providers;

use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Observers\AuditObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void { /* ... */ }

    public function boot(): void
    {
        // 1. Explicitly list the models to audit
        // This avoids the "abstract class" error and prevents logging system tables
        $models = [
            \App\Models\Company::class,
            \App\Models\CompanyPayment::class,
            \App\Models\Expense::class,
            \App\Models\ExpenseCategory::class,
            \App\Models\FloatRequest::class,
            \App\Models\Item::class,
            \App\Models\MpesaTransaction::class,
            \App\Models\Purchase::class,
            \App\Models\Sale::class,
            \App\Models\Shift::class,
            \App\Models\Supplier::class,           
            \App\Models\User::class,

        ];

        foreach ($models as $model) {
            $model::observe(AuditObserver::class);
        }

        // 2. Register Policies
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        // 3. Super Admin Bypass
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });
    }
}