<?php

namespace App\Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Requests\StoreRoleRequest;
use App\Modules\UserManagement\Requests\SyncRolePermissionsRequest;
use App\Modules\UserManagement\Requests\UpdateRoleRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::query()
            ->withCount(['permissions', 'userRoles'])
            ->with(['permissions:id,permission_name,slug,resource,action'])
            ->orderBy('role_name')
            ->get(['id', 'role_name', 'description', 'is_active']);

        return Inertia::render('UserManagement/Roles/Index', [
            'roles' => $roles->map(fn ($role) => [
                'id' => $role->id,
                'role_name' => $role->role_name,
                'description' => $role->description,
                'is_active' => (bool) $role->is_active,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->user_roles_count,
                'permissions' => $role->permissions->map(fn ($p) => [
                    'id' => $p->id,
                    'permission_name' => $p->permission_name,
                    'slug' => $p->slug,
                    'resource' => $p->resource ?? 'general',
                    'action' => $p->action ?? '',
                ])->values(),
            ]),
            'can' => [
                'create' => Gate::allows('create', Role::class),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('UserManagement/Roles/Create', [
            'permissionGroups' => $this->getPermissionGroups(),
        ]);
    }

    public function show(Role $role)
    {
        return Inertia::render('UserManagement/Roles/Show', [
            'role' => [
                'id' => $role->id,
                'role_name' => $role->role_name,
                'description' => $role->description,
                'is_active' => (bool) $role->is_active,
                'permission_ids' => $role->permissions()->pluck('tbl_permission.id'),
            ],
            'permissionGroups' => $this->getPermissionGroups(),
        ]);
    }

    public function edit(Role $role)
    {
        return Inertia::render('UserManagement/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'role_name' => $role->role_name,
                'description' => $role->description,
                'is_active' => (bool) $role->is_active,
                'permission_ids' => $role->permissions()->pluck('tbl_permission.id'),
            ],
            'permissionGroups' => $this->getPermissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        $this->authorize('create', Role::class);

        $validated = $request->validated();

        Gate::authorize('assignPermissions', [Role::class, $validated['permission_ids']]);

        DB::transaction(function () use ($validated) {
            $role = Role::create([
                'role_name' => $validated['role_name'],
                'description' => $validated['description'] ?? '',
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Use syncPermissions — prevents duplicates + invalidates cache
            $role->syncPermissions($validated['permission_ids']);

            session()->flash('success', 'Role created successfully.');
        });

        return redirect()->route('user-management.roles.index');
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->authorize('update', $role);

        $validated = $request->validated();

        Gate::authorize('assignPermissions', [Role::class, $validated['permission_ids']]);

        DB::transaction(function () use ($role, $validated) {
            $role->update([
                'role_name' => $validated['role_name'],
                'description' => $validated['description'] ?? '',
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Use syncPermissions — prevents duplicates + invalidates cache
            $role->syncPermissions($validated['permission_ids']);

            session()->flash('success', 'Role updated successfully.');
        });

        return redirect()->route('user-management.roles.index');
    }

    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        $inUse = DB::table('tbl_user_role')->where('role_id', $role->id)->count();
        if ($inUse > 0) {
            return back()->with('error', "Cannot delete: role is assigned to {$inUse} user(s).");
        }

        DB::transaction(function () use ($role) {
            $role->syncPermissions([]); // detach all + invalidate cache
            $role->delete();
        });

        return back()->with('success', 'Role deleted.');
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role)
    {
        $this->authorize('update', $role);

        $validated = $request->validated();

        DB::transaction(function () use ($role, $validated) {
            // Use syncPermissions — atomic, no duplicates, cache invalidated
            $role->syncPermissions($validated['permission_ids']);
            Gate::authorize('assign', $role);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Return permissions grouped by resource for the create/edit forms.
     *
     * @return array<int, array{group: string, permissions: array<int, array{id: int, permission_name: string, slug: string}>}>
     */
    private function getPermissionGroups(): array
    {
        return Permission::query()
            ->orderBy('resource')
            ->orderBy('permission_name')
            ->get(['id', 'permission_name', 'slug', 'resource'])
            ->groupBy('resource')
            ->map(fn ($perms, $resource) => [
                'group' => $resource,
                'permissions' => $perms->map(fn ($p) => [
                    'id' => $p->id,
                    'permission_name' => $p->permission_name,
                    'slug' => $p->slug,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
