<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Seed user statuses, permissions, roles, and role-permission mappings.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // ── User Statuses ──────────────────────────────────────────
        $statuses = [
            ['status_name' => 'Active', 'description' => 'Active user'],
            ['status_name' => 'Inactive', 'description' => 'Inactive user'],
            ['status_name' => 'Archive', 'description' => 'Archive user'],
        ];

        foreach ($statuses as $status) {
            DB::table('tbl_user_status')->updateOrInsert(
                ['status_name' => $status['status_name']],
                [
                    'description' => $status['description'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        // ── Permissions ────────────────────────────────────────────
        $permissions = [
            // Users
            ['permission_name' => 'View Users', 'slug' => 'users.view', 'description' => 'Can view user list', 'resource' => 'users', 'action' => 'view'],
            ['permission_name' => 'Create Users', 'slug' => 'users.create', 'description' => 'Can create users', 'resource' => 'users', 'action' => 'create'],
            ['permission_name' => 'Edit Users', 'slug' => 'users.edit', 'description' => 'Can edit users', 'resource' => 'users', 'action' => 'edit'],
            ['permission_name' => 'Delete Users', 'slug' => 'users.delete', 'description' => 'Can delete users', 'resource' => 'users', 'action' => 'delete'],
            ['permission_name' => 'Manage Users', 'slug' => 'users.manage', 'description' => 'Can access and manage users', 'resource' => 'users', 'action' => 'manage'],

            // Forms
            ['permission_name' => 'View Forms', 'slug' => 'forms.view', 'description' => 'Can view form list', 'resource' => 'forms', 'action' => 'view'],
            ['permission_name' => 'Create Forms', 'slug' => 'forms.create', 'description' => 'Can create forms', 'resource' => 'forms', 'action' => 'create'],
            ['permission_name' => 'Edit Forms', 'slug' => 'forms.edit', 'description' => 'Can edit forms', 'resource' => 'forms', 'action' => 'edit'],
            ['permission_name' => 'Delete Forms', 'slug' => 'forms.delete', 'description' => 'Can delete forms', 'resource' => 'forms', 'action' => 'delete'],
            ['permission_name' => 'Manage Forms', 'slug' => 'forms.manage', 'description' => 'Can access and manage forms', 'resource' => 'forms', 'action' => 'manage'],

            // Workflows
            ['permission_name' => 'View Workflows', 'slug' => 'workflows.view', 'description' => 'Can view workflow list', 'resource' => 'workflows', 'action' => 'view'],
            ['permission_name' => 'Create Workflows', 'slug' => 'workflows.create', 'description' => 'Can create workflows', 'resource' => 'workflows', 'action' => 'create'],
            ['permission_name' => 'Edit Workflows', 'slug' => 'workflows.edit', 'description' => 'Can edit workflows', 'resource' => 'workflows', 'action' => 'edit'],
            ['permission_name' => 'Delete Workflows', 'slug' => 'workflows.delete', 'description' => 'Can delete workflows', 'resource' => 'workflows', 'action' => 'delete'],
            ['permission_name' => 'Manage Workflows', 'slug' => 'workflows.manage', 'description' => 'Can access and manage workflows', 'resource' => 'workflows', 'action' => 'manage'],

            // Requests
            ['permission_name' => 'View Requests', 'slug' => 'requests.view', 'description' => 'Can view submitted requests', 'resource' => 'requests', 'action' => 'view'],
            ['permission_name' => 'Approve Requests', 'slug' => 'requests.approve', 'description' => 'Can approve requests', 'resource' => 'requests', 'action' => 'approve'],
            ['permission_name' => 'Reject Requests', 'slug' => 'requests.reject', 'description' => 'Can reject requests', 'resource' => 'requests', 'action' => 'reject'],
            ['permission_name' => 'Manage Requests', 'slug' => 'requests.manage', 'description' => 'Can manage all request submissions', 'resource' => 'requests', 'action' => 'manage'],

            // Form Access
            ['permission_name' => 'Access Student Forms', 'slug' => 'forms.student-access', 'description' => 'Can access student-submitted forms', 'resource' => 'forms', 'action' => 'student-access'],
            ['permission_name' => 'Access Staff Forms', 'slug' => 'forms.staff-access', 'description' => 'Can access staff-submitted forms', 'resource' => 'forms', 'action' => 'staff-access'],
            ['permission_name' => 'Access Public Forms', 'slug' => 'forms.public-access', 'description' => 'Can access publicly available forms', 'resource' => 'forms', 'action' => 'public-access'],

            // Dashboards
            ['permission_name' => 'Access Student Dashboard', 'slug' => 'dashboard.student', 'description' => 'Can access student dashboard', 'resource' => 'dashboard', 'action' => 'student'],
            ['permission_name' => 'Access Staff Dashboard', 'slug' => 'dashboard.staff', 'description' => 'Can access staff dashboard', 'resource' => 'dashboard', 'action' => 'staff'],
            ['permission_name' => 'Access Admin Dashboard', 'slug' => 'dashboard.admin', 'description' => 'Can access admin dashboard', 'resource' => 'dashboard', 'action' => 'admin'],

            // Facilities & Org
            ['permission_name' => 'Manage Facilities', 'slug' => 'facilities.manage', 'description' => 'Can create/edit/manage facilities', 'resource' => 'facilities', 'action' => 'manage'],
            ['permission_name' => 'Manage Users (Org)', 'slug' => 'manage_org.users', 'description' => 'Can manage users, roles, org structure scoped', 'resource' => 'manage_org', 'action' => 'users'],

            // Admin Submissions
            ['permission_name' => 'View All Submissions', 'slug' => 'submissions.view', 'description' => 'See all pending submissions across the system', 'resource' => 'submissions', 'action' => 'view'],
            ['permission_name' => 'Override Approvals', 'slug' => 'submissions.override', 'description' => 'Approve/Reject any step regardless of assignment', 'resource' => 'submissions', 'action' => 'override'],

            // Performance
            ['permission_name' => 'Staff Performance Report', 'slug' => 'performance.view', 'description' => 'Can view staff performance metrics', 'resource' => 'performance', 'action' => 'view'],

            // Roles & Organizations (granular delegation)
            ['permission_name' => 'Manage Roles', 'slug' => 'roles.manage', 'description' => 'Can create, edit, and delete roles and their permissions', 'resource' => 'roles', 'action' => 'manage'],
            ['permission_name' => 'Manage Organizations', 'slug' => 'organizations.manage', 'description' => 'Can create, edit, and delete organizations, departments, and positions', 'resource' => 'organizations', 'action' => 'manage'],

            // Error Reports
            ['permission_name' => 'Manage Error Reports', 'slug' => 'error-reports.manage', 'description' => 'Can view and triage bug reports', 'resource' => 'error-reports', 'action' => 'manage'],
        ];

        foreach ($permissions as $permission) {
            DB::table('tbl_permission')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'permission_name' => $permission['permission_name'],
                    'description' => $permission['description'],
                    'resource' => $permission['resource'],
                    'action' => $permission['action'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        // ── Roles ──────────────────────────────────────────────────
        $roles = [
            ['role_name' => 'Admin',   'description' => 'Full system access',    'is_active' => 1],
            ['role_name' => 'Staff',   'description' => 'Can review and approve/reject submissions', 'is_active' => 1],
            ['role_name' => 'Student', 'description' => 'Can submit forms and track requests', 'is_active' => 1],
        ];

        foreach ($roles as $role) {
            DB::table('tbl_role')->updateOrInsert(
                ['role_name' => $role['role_name']],
                [
                    'description' => $role['description'],
                    'is_active' => $role['is_active'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        // ── Role → Permission mappings ─────────────────────────────
        $permissionIds = DB::table('tbl_permission')->pluck('id', 'slug');
        $roleIds = DB::table('tbl_role')->pluck('id', 'role_name');

        $rolePermissionSlugs = [
            'Admin' => [
                'users.view', 'users.create', 'users.edit', 'users.delete', 'users.manage',
                'forms.view', 'forms.create', 'forms.edit', 'forms.delete', 'forms.manage',
                'workflows.view', 'workflows.create', 'workflows.edit', 'workflows.delete', 'workflows.manage',
                'requests.view', 'requests.approve', 'requests.reject', 'requests.manage',
                'dashboard.admin', 'facilities.manage', 'manage_org.users', 'submissions.view', 'submissions.override',
                'performance.view', 'error-reports.manage',
            ],
            'Staff' => [
                'requests.view', 'requests.approve', 'requests.reject', 'requests.manage',
                'forms.staff-access', 'forms.public-access', 'dashboard.staff',
            ],
            'Student' => [
                'forms.view', 'forms.student-access', 'forms.public-access', 'dashboard.student',
            ],
        ];

        $rolePermissions = [];
        foreach ($rolePermissionSlugs as $roleName => $slugs) {
            $roleId = $roleIds[$roleName] ?? null;

            if (! $roleId) {
                continue;
            }

            foreach ($slugs as $slug) {
                $permissionId = $permissionIds[$slug] ?? null;

                if (! $permissionId) {
                    continue;
                }

                $rolePermissions[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($rolePermissions)) {
            DB::table('tbl_role_permission')->upsert(
                $rolePermissions,
                ['role_id', 'permission_id'],
                ['updated_at']
            );
        }

        $staffRoleId = $roleIds['Staff'] ?? null;
        $studentDashboardPermissionId = $permissionIds['dashboard.student'] ?? null;

        if ($staffRoleId && $studentDashboardPermissionId) {
            DB::table('tbl_role_permission')
                ->where('role_id', $staffRoleId)
                ->where('permission_id', $studentDashboardPermissionId)
                ->delete();
        }
    }
}
