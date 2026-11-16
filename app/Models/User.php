<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Access\AccountStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'department_id',
        'position',
        'account_status',
        'portal_access',
        'portal_approved_at',
        'locked_until',
        'auth_version',
        'last_login_at',
        'access_scope',
        'password',
        'phone',
        'profile_image',
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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'portal_approved_at' => 'datetime',
        'locked_until' => 'datetime',
        'portal_access' => 'boolean',
        'access_scope' => 'array',
        'password' => 'hashed',
    ];

    public function department()
    {
        return $this->belongsTo(StaffDepartment::class, 'department_id');
    }

    public function approvalRequests()
    {
        return $this->hasMany(ApprovalRequest::class, 'requester_id');
    }

    public function isActiveStaff(): bool
    {
        return ($this->account_status ?? 'active') === 'active';
    }

    public function hasPortalAccess(): bool
    {
        // Existing rows default portal_access to true; a null means "not
        // explicitly revoked", which we treat as approved.
        return $this->portal_access !== false;
    }

    public function isLocked(): bool
    {
        return $this->account_status === AccountStatus::LOCKED
            && ($this->locked_until === null || $this->locked_until->isFuture());
    }

    /**
     * Full eligibility for the staff portal: an active account that has been
     * explicitly approved for portal access.
     */
    public function canAccessPortal(): bool
    {
        if (! AccountStatus::isEligible($this->account_status)) {
            return false;
        }

        return $this->hasPortalAccess();
    }

    public function accountStatusLabel(): string
    {
        return AccountStatus::label($this->account_status);
    }

    /**
     * Invalidate every session issued for this account (any device). Bumping
     * `auth_version` is enforced by EnforceCurrentSessionVersion middleware and
     * works with the file session driver as well as database sessions.
     */
    public function revokeSessions(): void
    {
        $this->forceFill([
            'auth_version' => (int) $this->auth_version + 1,
            'remember_token' => null,
        ])->save();

        if (config('session.driver') === 'database') {
            try {
                \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $this->id)->delete();
            } catch (\Throwable) {
                // sessions table may not exist for other drivers
            }
        }
    }
}
