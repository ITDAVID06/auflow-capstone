<?php

namespace App\Modules\UserManagement\Controllers;

use App\Actions\UserManagement\CreateUserAction;
use App\Http\Controllers\Controller;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserStatus;
use App\Modules\UserManagement\Requests\StoreUserRequest;
use App\Modules\UserManagement\Requests\UpdateUserRequest;
use App\Modules\UserManagement\Resources\UserResource;
use App\Modules\UserManagement\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString() ?: null;
        $status = strtolower($request->string('status')->toString() ?: 'all');
        $perPage = max(1, min((int) $request->integer('per_page', 12), 100));

        $validStatuses = ['all', 'active', 'inactive', 'archive'];
        if (! in_array($status, $validStatuses, true)) {
            $status = 'all';
        }

        $sortColumn = $request->string('sort')->toString() ?: null;
        $sortDirection = strtolower($request->string('direction')->toString() ?: 'asc');
        $sortDirection = $sortDirection === 'desc' ? 'desc' : 'asc';

        $validSortColumns = ['name', 'email', 'status', 'created_at'];
        if (! in_array($sortColumn, $validSortColumns, true)) {
            $sortColumn = null;
        }

        $usersQuery = User::with([
            'profile:account_id,first_name,last_name,middle_name,student_id,employee_id,phone,address,date_of_birth,gender,profile_picture',
            'status:id,status_name',
            'roles:id,account_id,role_id',
            'roles.role:id,role_name',
        ])
            ->select(['account_id', 'username', 'email', 'user_status_id', 'created_at']);

        match ($sortColumn) {
            'name' => $usersQuery->orderBy('username', $sortDirection),
            'email' => $usersQuery->orderBy('email', $sortDirection),
            'created_at' => $usersQuery->orderBy('created_at', $sortDirection),
            'status' => $usersQuery->orderBy(
                \App\Modules\UserManagement\Models\UserStatus::select('status_name')
                    ->whereColumn('tbl_user_status.id', 'tbl_user.user_status_id')
                    ->limit(1),
                $sortDirection
            ),
            default => $usersQuery->orderByDesc('created_at'),
        };

        if ($status === 'archive') {
            $usersQuery->where('user_status_id', 3);
        } elseif ($status === 'active') {
            $usersQuery->whereHas('status', fn ($q) => $q->whereRaw('LOWER(status_name) = ?', ['active']));
        } elseif ($status === 'inactive') {
            $usersQuery->whereHas('status', fn ($q) => $q->whereRaw('LOWER(status_name) = ?', ['inactive']));
        } else {
            $usersQuery->where('user_status_id', '!=', 3);
        }

        if ($search) {
            $searchLike = '%'.$search.'%';
            $usersQuery->where(function ($query) use ($searchLike): void {
                $query->where('username', 'like', $searchLike)
                    ->orWhere('email', 'like', $searchLike)
                    ->orWhereHas('profile', function ($profileQuery) use ($searchLike): void {
                        $profileQuery->where('first_name', 'like', $searchLike)
                            ->orWhere('last_name', 'like', $searchLike);
                    });
            });
        }

        $usersPaginator = $usersQuery
            ->paginate($perPage)
            ->withQueryString();

        $metricsQuery = User::query();
        $totalUsers = (clone $metricsQuery)->count();
        $activeCount = (clone $metricsQuery)
            ->whereHas('status', fn ($q) => $q->whereRaw('LOWER(status_name) = ?', ['active']))
            ->count();
        $inactiveCount = (clone $metricsQuery)
            ->whereHas('status', fn ($q) => $q->whereRaw('LOWER(status_name) = ?', ['inactive']))
            ->count();
        $archivedCount = (clone $metricsQuery)
            ->whereHas('status', fn ($q) => $q->whereRaw('LOWER(status_name) = ?', ['archive']))
            ->count();

        $rolesWithPermissions = Role::query()
            ->select(['id', 'role_name', 'description', 'is_active'])
            ->with(['permissions:id,permission_name,slug,resource,action'])
            ->get();
        $statuses = UserStatus::query()->get(['id', 'status_name']);

        return Inertia::render('UserManagement/Index', [
            'users' => UserResource::collection($usersPaginator->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $usersPaginator->currentPage(),
                'last_page' => $usersPaginator->lastPage(),
                'per_page' => $usersPaginator->perPage(),
                'total' => $usersPaginator->total(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
                'sort' => $sortColumn,
                'direction' => $sortDirection,
            ],
            'metrics' => [
                'total' => $totalUsers,
                'active' => $activeCount,
                'inactive' => $inactiveCount,
                'archived' => $archivedCount,
            ],
            'roles' => $rolesWithPermissions,
            'statuses' => $statuses,
            'permissions' => Permission::query()->get(['id', 'permission_name', 'slug', 'resource', 'action']),
        ]);
    }

    public function create(): \Inertia\Response
    {
        return Inertia::render('UserManagement/Create', [
            'statuses' => UserStatus::all(),
            'roles' => Role::where('is_active', 1)->get(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $createUserAction): RedirectResponse
    {
        $createUserAction->execute($request->validated());

        return redirect()
            ->route('user-management.users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        $user->load(['profile', 'status', 'roles.role']);

        return Inertia::render('UserManagement/View', [
            'user' => $user,
        ]);
    }

    public function edit(User $user): \Inertia\Response
    {
        $this->authorize('view', $user);

        $user->load(['profile', 'status', 'roles.role']);

        return Inertia::render('UserManagement/Edit', [
            'user' => new UserResource($user),
            'statuses' => UserStatus::all(),
            'roles' => Role::where('is_active', 1)->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UserService $service): RedirectResponse
    {
        $this->authorize('update', $user);

        $service->update($user->account_id, $request->validated());

        return redirect()
            ->route('user-management.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        DB::transaction(function () use ($user) {
            $user->update(['user_status_id' => 3]); // Archive
            $user->directRoles()->detach();
        });

        return back()->with('success', 'User archived and roles removed.');
    }
}
