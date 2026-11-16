<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoleDecisionPolicy;
use App\Services\Access\SessionManager;
use App\Services\Admin\AuditLogger;
use App\Support\Access\AbilityCatalog;
use App\Support\Access\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Roles that must never be deleted or have their name changed.
     */
    public const PROTECTED_ROLES = ['super_admin', 'admin'];

    public function __construct(protected AuditLogger $audit, protected SessionManager $sessions) {}

    public function index()
    {
        $roles = Role::withCount(['permissions', 'users'])->orderBy('name')->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        return view('admin.roles.create', [
            'modules' => PermissionCatalog::modules(),
            'abilities' => AbilityCatalog::all(),
            'role' => new Role(['guard_name' => 'web']),
            'granted' => [],
            'policies' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $this->syncPermissions($role, $data['permissions'] ?? []);
        $this->syncPolicies($role, $data['policies'] ?? []);

        $this->audit->log('role.created', $role, [], [
            'name' => $role->name,
            'permissions' => $data['permissions'] ?? [],
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role)
    {
        return view('admin.roles.edit', [
            'modules' => PermissionCatalog::modules(),
            'abilities' => AbilityCatalog::all(),
            'role' => $role,
            'granted' => $role->permissions->pluck('name')->all(),
            'policies' => RoleDecisionPolicy::where('role_id', $role->id)->get()->keyBy('ability'),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validated($request, $role);

        if (! in_array($role->name, self::PROTECTED_ROLES, true) && $role->name !== $data['name']) {
            $before = $role->name;
            $role->name = $data['name'];
            $role->save();
            $this->audit->log('role.renamed', $role, ['name' => $before], ['name' => $role->name]);
        }

        $before_permissions = $role->permissions()->pluck('name')->sort()->values()->all();

        if ($role->name === 'super_admin') {
            // Super admin always retains every permission.
            $this->syncPermissions($role, PermissionCatalog::all());
        } else {
            $this->syncPermissions($role, $data['permissions'] ?? []);
            $this->syncPolicies($role, $data['policies'] ?? []);
        }

        $after_permissions = $role->permissions()->pluck('name')->sort()->values()->all();

        // A change to a role's effective permissions revokes the sessions of
        // every holder so the new policy applies to their very next request.
        if ($before_permissions !== $after_permissions) {
            $this->sessions->revokeForRole($role);
        }

        $this->audit->log('role.updated', $role, ['permissions' => $before_permissions], [
            'permissions' => $after_permissions,
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    public function duplicate(Role $role)
    {
        $copy = Role::create([
            'name' => $this->uniqueName($role->name.' copy'),
            'guard_name' => 'web',
        ]);

        $copy->syncPermissions($role->permissions);

        foreach (RoleDecisionPolicy::where('role_id', $role->id)->get() as $policy) {
            RoleDecisionPolicy::create([
                'role_id' => $copy->id,
                'ability' => $policy->ability,
                'mode' => $policy->mode,
                'limits' => $policy->limits,
            ]);
        }

        $this->audit->log('role.duplicated', $copy, [], ['from' => $role->name]);

        return redirect()->route('admin.roles.edit', $copy)->with('success', 'Role duplicated.');
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return back()->withErrors(['role' => 'This system role cannot be deleted.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Cannot delete a role that is still assigned to staff.']);
        }

        $before = $role->name;
        $role->delete();

        $this->audit->log('role.deleted', null, ['name' => $before], []);

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }

    protected function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
            'policies' => ['array'],
            'policies.*.mode' => ['required', 'in:allowed,limited,requires_approval,prohibited'],
            'policies.*.limit' => ['nullable', 'numeric', 'min:0'],
        ], [
            'name.regex' => 'Role names may contain lowercase letters, numbers and underscores only.',
        ]);

        if (in_array($request->input('name'), self::PROTECTED_ROLES, true) && $role?->name !== $request->input('name')) {
            throw ValidationException::withMessages(['name' => 'That role name is reserved.']);
        }

        return $data;
    }

    protected function syncPermissions(Role $role, array $permissions): void
    {
        $valid = Permission::whereIn('name', $permissions)->where('guard_name', 'web')->pluck('name')->all();
        $role->syncPermissions($valid);
    }

    protected function syncPolicies(Role $role, array $policies): void
    {
        RoleDecisionPolicy::where('role_id', $role->id)->delete();

        foreach ($policies as $ability => $config) {
            if (! AbilityCatalog::get($ability)) {
                continue;
            }

            $limits = null;
            if (($config['mode'] ?? null) === 'limited' && isset($config['limit']) && $config['limit'] !== '') {
                $definition = AbilityCatalog::get($ability);
                $limits = $definition['limit_key'] ? [$definition['limit_key'] => (float) $config['limit']] : null;
            }

            RoleDecisionPolicy::create([
                'role_id' => $role->id,
                'ability' => $ability,
                'mode' => $config['mode'],
                'limits' => $limits,
            ]);
        }
    }

    protected function uniqueName(string $base): string
    {
        $slug = Str::slug($base, '_');
        $name = $slug;
        $i = 1;

        while (Role::where('name', $name)->exists()) {
            $name = $slug.'_'.$i++;
        }

        return $name;
    }
}
