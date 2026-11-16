<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Logistics\LogisticsAdmin;
use App\Services\Admin\AuditLogger;
use App\Support\Access\AccountStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class LogisticsStaffController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index()
    {
        $staff = LogisticsAdmin::orderBy('id')->get();

        return view('admin.logistics-staff.index', compact('staff'));
    }

    public function create()
    {
        return view('admin.logistics-staff.create', [
            'roles' => LogisticsAdmin::ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertCanAssignRole($data['role']);

        $staff = LogisticsAdmin::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'permissions' => $data['permissions'] ?? null,
            'is_active' => true,
            'account_status' => AccountStatus::ACTIVE,
            'portal_access' => true,
            'portal_approved_at' => now(),
            'auth_version' => 0,
        ]);

        $this->audit->log('logistics.staff.created', $staff, [], [
            'email' => $staff->email,
            'role' => $data['role'],
            'status' => $staff->account_status,
            'portal_access' => true,
        ]);

        return redirect()->route('admin.logistics-staff.index')
            ->with('success', 'Logistics staff account created.');
    }

    public function show(LogisticsAdmin $logisticsStaff)
    {
        return redirect()->route('admin.logistics-staff.edit', $logisticsStaff);
    }

    public function edit(LogisticsAdmin $logisticsStaff)
    {
        return view('admin.logistics-staff.edit', [
            'staff' => $logisticsStaff,
            'roles' => LogisticsAdmin::ROLES,
        ]);
    }

    public function update(Request $request, LogisticsAdmin $logisticsStaff)
    {
        $this->assertCanManageAccount($logisticsStaff);
        $data = $this->validated($request, $logisticsStaff);
        $this->assertCanAssignRole($data['role'], $logisticsStaff);

        if ($this->wouldLoseLastSuperAdmin($logisticsStaff, $data['role'])) {
            return back()->withErrors(['role' => 'At least one active logistics super administrator must remain.'])->withInput();
        }

        $before = [
            'role' => $logisticsStaff->role,
            'status' => $logisticsStaff->account_status,
            'portal_access' => (bool) $logisticsStaff->portal_access,
        ];

        $newStatus = $data['account_status'] ?? (($data['is_active'] ?? $logisticsStaff->is_active) ? AccountStatus::ACTIVE : AccountStatus::SUSPENDED);
        $hadPortal = (bool) $logisticsStaff->hasPortalAccess();
        $newPortalAccess = array_key_exists('portal_access', $data)
            ? (bool) $data['portal_access']
            : $hadPortal;

        $logisticsStaff->fill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'permissions' => $data['permissions'] ?? null,
            'account_status' => $newStatus,
            'portal_access' => $newPortalAccess,
            'is_active' => AccountStatus::isEligible($newStatus) && (bool) ($data['is_active'] ?? true),
        ]);

        if (! empty($data['password'])) {
            $logisticsStaff->password = Hash::make($data['password']);
        }

        $logisticsStaff->save();

        $roleChanged = $before['role'] !== $data['role'];
        $statusChanged = $before['status'] !== $newStatus;
        $portalChanged = $newPortalAccess !== $hadPortal;

        if ($roleChanged || $statusChanged || $portalChanged || ! empty($data['password'])) {
            $logisticsStaff->revokeSessions();
        }

        $this->audit->log('logistics.staff.updated', $logisticsStaff, $before, [
            'role' => $data['role'],
            'status' => $logisticsStaff->account_status,
            'portal_access' => $newPortalAccess,
        ]);

        return redirect()->route('admin.logistics-staff.index')
            ->with('success', 'Logistics staff account updated.');
    }

    public function destroy(LogisticsAdmin $logisticsStaff)
    {
        $this->assertCanManageAccount($logisticsStaff);

        if ($logisticsStaff->isSuperAdmin() && $this->isLastSuperAdmin($logisticsStaff)) {
            return back()->withErrors(['staff' => 'Super administrator accounts cannot be deleted from here.']);
        }

        $before = ['email' => $logisticsStaff->email, 'role' => $logisticsStaff->role];
        $logisticsStaff->delete();

        $this->audit->log('logistics.staff.deleted', null, $before, []);

        return back()->with('success', 'Logistics staff account deleted.');
    }

    public function approveAccess(LogisticsAdmin $logisticsStaff)
    {
        if ($logisticsStaff->canAccessPortal() && $logisticsStaff->isActiveStaff()) {
            return back()->with('success', 'Logistics staff account is already active.');
        }

        $before = ['status' => $logisticsStaff->account_status, 'portal_access' => (bool) $logisticsStaff->portal_access];

        $logisticsStaff->forceFill([
            'account_status' => AccountStatus::ACTIVE,
            'portal_access' => true,
            'portal_approved_at' => now(),
            'is_active' => true,
        ])->save();

        $this->audit->log('logistics.staff.access_approved', $logisticsStaff, $before, [
            'status' => $logisticsStaff->account_status,
            'portal_access' => true,
        ]);

        return back()->with('success', 'Portal access approved for '.$logisticsStaff->name.'.');
    }

    public function revokeAccess(LogisticsAdmin $logisticsStaff)
    {
        if ($this->isLastSuperAdmin($logisticsStaff)) {
            return back()->withErrors(['staff' => 'Cannot revoke the last logistics super administrator.']);
        }

        $before = ['status' => $logisticsStaff->account_status, 'portal_access' => (bool) $logisticsStaff->portal_access];

        $logisticsStaff->forceFill([
            'account_status' => AccountStatus::REVOKED,
            'portal_access' => false,
            'is_active' => false,
        ])->save();

        $logisticsStaff->revokeSessions();

        $this->audit->log('logistics.staff.access_revoked', $logisticsStaff, $before, [
            'status' => $logisticsStaff->account_status,
            'portal_access' => false,
        ]);

        return back()->with('success', 'Portal access revoked for '.$logisticsStaff->name.'.');
    }

    public function toggleStatus(LogisticsAdmin $logisticsStaff)
    {
        if ($logisticsStaff->isActiveStaff() && $this->isLastSuperAdmin($logisticsStaff)) {
            return back()->withErrors(['staff' => 'Cannot deactivate the last logistics super administrator.']);
        }

        $before = $logisticsStaff->account_status;
        $logisticsStaff->account_status = $logisticsStaff->isActiveStaff() ? AccountStatus::SUSPENDED : AccountStatus::ACTIVE;
        $logisticsStaff->is_active = $logisticsStaff->account_status === AccountStatus::ACTIVE;
        $logisticsStaff->save();

        if ($before !== $logisticsStaff->account_status) {
            $logisticsStaff->revokeSessions();
        }

        $this->audit->log('logistics.staff.status_changed', $logisticsStaff, ['status' => $before], ['status' => $logisticsStaff->account_status]);

        return back()->with('success', 'Logistics staff account '.($logisticsStaff->account_status === AccountStatus::ACTIVE ? 'activated' : 'deactivated').'.');
    }

    public function revokeSessions(LogisticsAdmin $logisticsStaff)
    {
        $logisticsStaff->revokeSessions();

        $this->audit->log('logistics.staff.sessions_revoked', $logisticsStaff, [], []);

        return back()->with('success', 'Active sessions revoked for '.$logisticsStaff->name.'.');
    }

    public function audit(LogisticsAdmin $logisticsStaff)
    {
        return redirect()->route('admin.logistics-staff.edit', $logisticsStaff);
    }

    private function assertCanAssignRole(string $role, ?LogisticsAdmin $target = null): void
    {
        $actor = auth()->user();

        abort_unless($actor, 403);

        $actorIsSuperAdmin = method_exists($actor, 'hasRole') && $actor->hasRole('super_admin');
        $assignsSuperAdmin = $role === 'super_admin';
        $editsSuperAdmin = $target?->isSuperAdmin() ?? false;

        abort_unless($actorIsSuperAdmin || (! $assignsSuperAdmin && ! $editsSuperAdmin), 403, 'Only a super administrator can manage super administrator accounts.');
    }

    private function assertCanManageAccount(LogisticsAdmin $target): void
    {
        $actor = auth()->user();

        abort_unless($actor, 403);

        if ($target->isSuperAdmin() && ! (method_exists($actor, 'hasRole') && $actor->hasRole('super_admin'))) {
            abort(403, 'Only a super administrator can manage super administrator accounts.');
        }
    }

    protected function validated(Request $request, ?LogisticsAdmin $staff = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('logistics_admins', 'email')->ignore($staff?->id)],
            'phone' => 'nullable|string|max:50',
            'password' => $staff ? 'nullable|string|min:8' : 'required|string|min:8',
            'role' => ['required', Rule::in(array_keys(LogisticsAdmin::ROLES))],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:100',
            'is_active' => 'nullable|boolean',
            'account_status' => ['nullable', Rule::in(AccountStatus::all())],
            'portal_access' => 'nullable|boolean',
        ]);

        return $data;
    }

    private function isLastSuperAdmin(LogisticsAdmin $user): bool
    {
        if (! $user->isSuperAdmin()) {
            return false;
        }

        return LogisticsAdmin::where('role', 'super_admin')
            ->where('is_active', true)
            ->where('account_status', AccountStatus::ACTIVE)
            ->where('id', '!=', $user->id)
            ->count() === 0;
    }

    private function wouldLoseLastSuperAdmin(LogisticsAdmin $user, string $newRole): bool
    {
        if ($newRole === 'super_admin') {
            return false;
        }

        if (! $user->isSuperAdmin()) {
            return false;
        }

        return LogisticsAdmin::where('role', 'super_admin')
            ->where('is_active', true)
            ->where('account_status', AccountStatus::ACTIVE)
            ->where('id', '!=', $user->id)
            ->count() === 0;
    }
}
