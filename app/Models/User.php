<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Filament access control logic.
     * Allows any user with the 'admin' role access to all panels.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // 1. Universal bypass: Any user with the 'admin' role can access everything.
        if ($this->hasRole('admin', 'web')) {
            return true;
        }

        // 2. Strict access for Admin Panel (only admins - caught by #1)
        if ($panel->getId() === 'admin') {
            return false;
        }

        // 3. Access for Field Operations Panel for authorized roles
        if ($panel->getId() === 'field-operations') {
            return $this->hasAnyRole(['supervisor', 'field-operator'], 'web');
        }

        return false;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}