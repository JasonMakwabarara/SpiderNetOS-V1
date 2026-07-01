<?php

namespace App\Models;

use App\Support\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'is_super_admin',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function effectiveRole(): string
    {
        if ($this->is_super_admin) {
            return UserRole::SUPER_ADMIN;
        }

        return $this->role ?: UserRole::MEMBER;
    }

    public function isSuperAdmin(): bool
    {
        return $this->effectiveRole() === UserRole::SUPER_ADMIN;
    }

    public function isTenantAdmin(): bool
    {
        return UserRole::rank($this->effectiveRole()) >= UserRole::rank(UserRole::TENANT_ADMIN);
    }

    public function hasMinimumRole(string $role): bool
    {
        return UserRole::rank($this->effectiveRole()) >= UserRole::rank($role);
    }
}
