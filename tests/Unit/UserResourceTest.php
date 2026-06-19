<?php

namespace Tests\Unit;

use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserProfile;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\UserManagement\Models\UserStatus;
use App\Modules\UserManagement\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    public function test_it_maps_relative_profile_picture_to_public_disk_url(): void
    {
        $user = $this->makeUserWithProfilePicture('profile-pictures/avatar.jpg');

        $resource = (new UserResource($user))->toArray(new Request);

        $this->assertStringEndsWith('/profile-pictures/profile-pictures/avatar.jpg', $resource['profile']['profile_picture_url']);
    }

    public function test_it_preserves_absolute_profile_picture_url(): void
    {
        $absoluteUrl = 'https://cdn.example.com/avatar.jpg';
        $user = $this->makeUserWithProfilePicture($absoluteUrl);

        $resource = (new UserResource($user))->toArray(new Request);

        $this->assertSame($absoluteUrl, $resource['profile']['profile_picture_url']);
    }

    private function makeUserWithProfilePicture(string $profilePicture): User
    {
        $user = new User([
            'account_id' => 10,
            'username' => 'jdoe',
            'email' => 'jdoe@example.com',
            'created_at' => now(),
        ]);

        $user->setRelation('status', new UserStatus([
            'id' => 1,
            'status_name' => 'Active',
        ]));

        $user->setRelation('profile', new UserProfile([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'profile_picture' => $profilePicture,
        ]));

        $role = new Role([
            'id' => 1,
            'role_name' => 'Admin',
        ]);

        $userRole = new UserRole([
            'role_id' => 1,
        ]);
        $userRole->setRelation('role', $role);

        $user->setRelation('roles', new Collection([$userRole]));

        return $user;
    }
}
