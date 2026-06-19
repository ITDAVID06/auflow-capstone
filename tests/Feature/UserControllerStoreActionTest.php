<?php

namespace Tests\Feature;

use App\Actions\UserManagement\CreateUserAction;
use App\Modules\UserManagement\Controllers\UserController;
use Tests\TestCase;

class UserControllerStoreActionTest extends TestCase
{
    public function test_store_injects_create_user_action(): void
    {
        $method = new \ReflectionMethod(UserController::class, 'store');
        $parameters = $method->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertSame(
            CreateUserAction::class,
            $parameters[1]->getType()?->getName()
        );
    }
}
