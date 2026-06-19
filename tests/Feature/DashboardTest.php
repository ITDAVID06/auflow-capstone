<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    // use RefreshDatabase; // DISABLED: Use separate test database, don't wipe it

    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('Scaffold test uses App\\Models\\User (vanilla users table) which does not exist in AUFlow.');
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertOk();
    }
}
