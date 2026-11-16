<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\StaffDepartment;
use App\Models\User;
use App\Services\Access\SessionManager;
use App\Services\Admin\AuditLogger;
use App\Support\Access\AccountStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function __construct(protected AuditLogger $audit, protected SessionManager $sessions) {}

    public function index()
    {
        $users = User::with(['roles', 'department'])
            ->withCount(['approvalRequests as pending_approvals_count' => function ($q) {
                $q->where('status', 'pending');
            }])
            ->orderBy('id')
            ->get();

        return view('admin.staff.index', compact('users'));
    }

    public function create()
    {
        return view('admin.staff.create', [
            'roles' => $this->roles(),
            'departments' => StaffDepartment::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'department_id' => $data['department_id'] ?? null,
            'position' => $data['position'] ?? null,
            'account_status' => AccountStatus::ACTIVE,
            'portal_access' => true,
            'portal_approved_at' => now(),
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles([$data['role']]);

        $this->audit->log('staff.created', $user, [], [
            'email' => $user->email,
            'role' => $data['role'],
        ]);

        return redirect()->route('admin.staff.index')->with('success', 'Staff account created.');
    }

    public function show(User $staff)
    {
        $staff->load(['roles', 'department']);

        $auditLogs = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $staff->id)
            ->latest('id')
            ->limit(50)
            ->get();

        $approvals = ApprovalRequest::where('requester_id', $staff->id)
            ->latest('id')
            ->limit(25)
            ->get();

        return view('admin.staff.show', [
            'staff' => $staff,
            'auditLogs' => $auditLogs,
            'approvals' => $approvals,
            'permissions' => $staff->getAllPermissions()->pluck('name'),
        ]);
    }

    public function edit(User $staff)
    {
        return view('admin.staff.edit', [
            'user' => $staff,
            'roles' => $this->roles(),
            'departments' => StaffDepartment::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $staff)
    {
        $data = $this->validated($request, $staff);

        $before = ['role' => $staff->roles->first()?->name, 'department_id' => $staff->department_id, 'status' => $staff->account_status];

        if ($staff->id === Auth::id() && $data['role'] !== 'super_admin' && $this->isLastSuperAdmin($staff)) {
            return back()->withErrors(['role' => 'You cannot remove the last super admin.'])->withInput();
        }

        if ($this->wouldLoseLastSuperAdmin($staff, $data['role'])) {
            return back()->withErrors(['role' => 'At least one active super admin must remain.'])->withInput();
        }

        $newStatus = $data['account_status'] ?? $staff->account_status;
        $hadPortal = (bool) $staff->hasPortalAccess();
        $newPortalAccess = array_key_exists('portal_access', $data)
            ? (bool) $data['portal_access']
            : $hadPortal;

        $staff->fill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'department_id' => $data['department_id'] ?? null,
            'position' => $data['position'] ?? null,
            'account_status' => $newStatus,
            'portal_access' => $newPortalAccess,
        ]);

        if (! empty($data['password'])) {
            $staff->password = Hash::make($data['password']);
        }

        $staff->save();
        $staff->syncRoles([$data['role']]);

        // Security-sensitive change: role, status, portal access or a credential
        // reset invalidates every outstanding session for this account.
        $roleChanged = $before['role'] !== $data['role'];
        $statusChanged = $before['status'] !== $newStatus;
        $portalChanged = $newPortalAccess !== $hadPortal;

        if ($roleChanged || $statusChanged || $portalChanged || ! empty($data['password'])) {
            $this->sessions->revoke($staff);
        }

        $this->audit->log('staff.updated', $staff, $before, [
            'role' => $data['role'],
            'department_id' => $staff->department_id,
            'status' => $staff->account_status,
            'portal_access' => $newPortalAccess,
        ]);

        return redirect()->route('admin.staff.index')->with('success', 'Staff account updated.');
    }

    /**
     * Approve a pending staff account for portal access.
     */
    public function approveAccess(User $staff)
    {
        if ($staff->canAccessPortal() && $staff->isActiveStaff()) {
            return back()->with('success', 'Staff account is already active.');
        }

        $before = ['status' => $staff->account_status, 'portal_access' => (bool) $staff->portal_access];

        $staff->forceFill([
            'account_status' => AccountStatus::ACTIVE,
            'portal_access' => true,
            'portal_approved_at' => now(),
        ])->save();

        $this->audit->log('staff.access_approved', $staff, $before, [
            'status' => $staff->account_status,
            'portal_access' => true,
        ]);

        return back()->with('success', 'Portal access approved for '.$staff->name.'.');
    }

    /**
     * Revoke portal access entirely and end active sessions.
     */
    public function revokeAccess(User $staff)
    {
        if ($staff->id === Auth::id()) {
            return back()->withErrors(['staff' => 'You cannot revoke your own access.']);
        }

        if ($this->isLastSuperAdmin($staff)) {
            return back()->withErrors(['staff' => 'Cannot revoke the last super admin.']);
        }

        $before = ['status' => $staff->account_status, 'portal_access' => (bool) $staff->portal_access];

        $staff->forceFill([
            'account_status' => AccountStatus::REVOKED,
            'portal_access' => false,
        ])->save();

        $this->sessions->revoke($staff);

        $this->audit->log('staff.access_revoked', $staff, $before, [
            'status' => $staff->account_status,
            'portal_access' => false,
        ]);

        return back()->with('success', 'Portal access revoked for '.$staff->name.'.');
    }

    public function toggleStatus(User $staff)
    {
        if ($staff->id === Auth::id()) {
            return back()->withErrors(['staff' => 'You cannot deactivate your own account.']);
        }

        if ($staff->isActiveStaff() && $this->isLastSuperAdmin($staff)) {
            return back()->withErrors(['staff' => 'Cannot deactivate the last super admin.']);
        }

        $before = $staff->account_status;
        $staff->account_status = $staff->isActiveStaff() ? AccountStatus::SUSPENDED : AccountStatus::ACTIVE;
        $staff->save();

        // Deactivation ends all outstanding sessions immediately.
        if ($before !== $staff->account_status) {
            $this->sessions->revoke($staff);
        }

        $this->audit->log('staff.status_changed', $staff, ['status' => $before], ['status' => $staff->account_status]);

        return back()->with('success', 'Staff account '.($staff->account_status === AccountStatus::ACTIVE ? 'activated' : 'deactivated').'.');
    }

    public function revokeSessions(User $staff)
    {
        $this->sessions->revoke($staff);

        $this->audit->log('staff.sessions_revoked', $staff, [], []);

        return back()->with('success', 'Active sessions revoked for '.$staff->name.'.');
    }

    public function destroy(User $staff)
    {
        if ($staff->id === Auth::id()) {
            return back()->withErrors(['staff' => 'You cannot delete your own account.']);
        }

        if ($this->isLastSuperAdmin($staff)) {
            return back()->withErrors(['staff' => 'You cannot delete the last super admin.']);
        }

        $before = ['email' => $staff->email, 'role' => $staff->roles->first()?->name];
        $staff->delete();

        $this->audit->log('staff.deleted', null, $before, []);

        return back()->with('success', 'Staff account deleted.');
    }

    public function audit(User $staff)
    {
        $logs = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $staff->id)
            ->latest('id')
            ->paginate(50);

        return view('admin.staff.audit', compact('staff', 'logs'));
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in($this->roleNames())],
            'department_id' => ['nullable', 'exists:staff_departments,id'],
            'position' => ['nullable', 'string', 'max:120'],
            'account_status' => ['nullable', Rule::in(AccountStatus::all())],
            'portal_access' => ['nullable', 'boolean'],
        ]);
    }

    protected function roles()
    {
        return Role::where('guard_name', 'web')->orderBy('name')->get();
    }

    protected function roleNames(): array
    {
        return Role::where('guard_name', 'web')->orderBy('name')->pluck('name')->all();
    }

    protected function isLastSuperAdmin(User $user): bool
    {
        if (! $user->hasRole('super_admin')) {
            return false;
        }

        return User::role('super_admin')
            ->where('account_status', 'active')
            ->where('id', '!=', $user->id)
            ->count() === 0;
    }

    protected function wouldLoseLastSuperAdmin(User $user, string $newRole): bool
    {
        if ($newRole === 'super_admin') {
            return false;
        }

        if (! $user->hasRole('super_admin')) {
            return false;
        }

        return User::role('super_admin')->where('id', '!=', $user->id)->count() === 0;
    }
}
