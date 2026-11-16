<?php

namespace App\Models\Logistics;

use App\Support\Access\AccountStatus;
use App\Support\Access\LogisticsPermissionCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class LogisticsAdmin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guard = 'logistics_admin';

    protected $table = 'logistics_admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role', // super_admin, admin, dispatcher, support
        'permissions',
        'is_active',
        'account_status',
        'portal_access',
        'portal_approved_at',
        'locked_until',
        'auth_version',
        'last_login_at',
        'profile_image',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'permissions' => 'array',
        'is_active' => 'boolean',
        'portal_access' => 'boolean',
        'portal_approved_at' => 'datetime',
        'locked_until' => 'datetime',
        'auth_version' => 'integer',
        'last_login_at' => 'datetime',
    ];

    public const ROLES = [
        'super_admin' => 'Super Administrator',
        'admin' => 'Administrator',
        'dispatcher' => 'Dispatcher',
        'support' => 'Support Agent',
    ];

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isActiveStaff(): bool
    {
        return $this->is_active && ($this->account_status ?? AccountStatus::ACTIVE) === AccountStatus::ACTIVE;
    }

    public function hasPortalAccess(): bool
    {
        return $this->portal_access !== false;
    }

    public function canAccessPortal(): bool
    {
        if (! $this->is_active || ! AccountStatus::isEligible($this->account_status)) {
            return false;
        }

        return $this->hasPortalAccess();
    }

    public function revokeSessions(): void
    {
        $this->forceFill([
            'auth_version' => (int) $this->auth_version + 1,
            'remember_token' => null,
        ])->save();
    }

    public function canAccessLogisticsModule(string $module): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! in_array($module, LogisticsPermissionCatalog::modules(), true)) {
            return false;
        }

        if ($this->hasPermission(LogisticsPermissionCatalog::permission($module))) {
            return true;
        }

        return LogisticsPermissionCatalog::roleCanAccess($this->role, $module);
    }
}
