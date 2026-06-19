<?php

namespace App\Modules\UserManagement\Services;

use App\Actions\UserManagement\CreateUserAction;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function __construct(
        private readonly CreateUserAction $createUserAction
    ) {}

    public function store(array $data): User
    {
        return $this->createUserAction->execute($data);
    }

    public function update(int $accountId, array $data): User
    {
        $actorId = auth()->user()?->account_id;
        if (! $actorId) {
            throw new AuthorizationException('Unauthenticated role assignment is not allowed.');
        }

        return DB::transaction(function () use ($accountId, $data, $actorId) {
            // ⬇️ Enforce assignable roles
            $this->assertAssignableRoles($data['role_ids'] ?? []);

            $user = User::findOrFail($accountId);

            $user->update([
                'username' => $data['username'],
                'email' => $data['email'],
                'user_status_id' => $data['user_status_id'],
            ]);

            if (! empty($data['password'])) {
                $user->update(['password' => Hash::make($data['password'])]);
            }

            $profile = $user->profile()->updateOrCreate(
                ['account_id' => $user->account_id],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'student_id' => $data['student_id'] ?? null,
                    'position' => $data['position'] ?? '', // legacy field
                    'employee_id' => $data['employee_id'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'gender' => $data['gender'] ?? null,
                ]
            );

            if (! empty($data['profile_picture'])) {
                if ($profile->profile_picture) {
                    Storage::disk('profile-pictures')->delete($profile->profile_picture);
                }
                $profile->profile_picture = $data['profile_picture']->store('', 'profile-pictures');
                $profile->save();
            }

            // Sync roles atomically instead of delete-all + re-insert
            $existingRoleIds = UserRole::where('account_id', $accountId)
                ->pluck('role_id')
                ->all();
            $newRoleIds = $data['role_ids'] ?? [];

            $toDelete = array_diff($existingRoleIds, $newRoleIds);
            $toAdd = array_diff($newRoleIds, $existingRoleIds);

            if (! empty($toDelete)) {
                UserRole::where('account_id', $accountId)
                    ->whereIn('role_id', $toDelete)
                    ->delete();
            }

            foreach ($toAdd as $roleId) {
                UserRole::create([
                    'account_id' => $accountId,
                    'role_id' => $roleId,
                    'assigned_date' => now(),
                    'is_active' => 1,
                    'assigned_by' => $actorId,
                ]);
            }

            return $user;
        });
    }

    private function assertAssignableRoles(array $roleIds): void
    {
        foreach (Role::with('permissions')->whereIn('id', $roleIds)->get() as $role) {
            Gate::authorize('assign', $role);
        }
    }
}
