<?php

namespace App\Actions\UserManagement;

use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\UserManagement\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function __construct(
        private readonly PermissionService $permissionService
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): User
    {
        $actorId = auth()->user()?->account_id;
        if (! $actorId) {
            throw new AuthorizationException('Unauthenticated role assignment is not allowed.');
        }

        $user = DB::transaction(function () use ($data, $actorId): User {
            $this->assertAssignableRoles($data['role_ids'] ?? []);

            $user = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password'] ?? config('auth.default_password', 'Password123!')),
                'user_status_id' => $data['user_status_id'],
            ]);
            $user->must_change_password = true;
            $user->save();

            $profile = $user->profile()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'student_id' => $data['student_id'] ?? null,
                'position' => $data['position'] ?? '',
                'employee_id' => $data['employee_id'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
            ]);

            if (! empty($data['profile_picture'])) {
                $profile->profile_picture = $data['profile_picture']->store('', 'profile-pictures');
                $profile->save();
            }

            foreach ($data['role_ids'] as $roleId) {
                UserRole::create([
                    'account_id' => $user->account_id,
                    'role_id' => $roleId,
                    'assigned_date' => now(),
                    'is_active' => 1,
                    'assigned_by' => $actorId,
                ]);
            }

            return $user;
        });

        $this->permissionService->invalidateUserCache($user);

        return $user;
    }

    /**
     * @param  array<int, int|string>  $roleIds
     */
    private function assertAssignableRoles(array $roleIds): void
    {
        foreach (Role::with('permissions')->whereIn('id', $roleIds)->get() as $role) {
            Gate::authorize('assign', $role);
        }
    }
}
