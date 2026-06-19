<?php

namespace Tests\Unit;

use App\Modules\UserManagement\Models\User;
use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    public function test_must_change_password_is_not_mass_assignable(): void
    {
        $user = new User(['must_change_password' => true]);
        $this->assertNull($user->must_change_password);
    }

    public function test_remember_token_is_hidden_from_serialisation(): void
    {
        $user = new User;
        $user->remember_token = 'secret-token';

        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }
}
