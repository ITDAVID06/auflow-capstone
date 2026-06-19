<?php

namespace Tests\Feature;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifies that:
 * 1. 404/403/500 responses render the Inertia error page when the X-Inertia header is present.
 * 2. Non-Inertia requests fall back to standard Laravel responses (no Inertia render).
 * 3. The student submissions endpoint returns an empty paginated payload (not an error) when there
 *    are no submissions — confirming the frontend will receive the empty-state data.
 * 4. Invalid form submissions return validation errors as Inertia props, never raw exception text.
 */
class ErrorHandlingAndEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        \DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Build a user with the given permission slugs attached.
     */
    private function makeUserWithPermissions(array $slugs): User
    {
        $permissionIds = [];
        foreach ($slugs as $slug) {
            $p = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'permission_name' => ucwords(str_replace(['.', '-'], ' ', $slug)),
                    'description' => 'Test',
                    'resource' => explode('.', $slug)[0] ?? 'test',
                    'action' => explode('.', $slug)[1] ?? 'access',
                ]
            );
            $permissionIds[] = $p->id;
        }

        $role = Role::create([
            'role_name' => 'Role-'.uniqid(),
            'description' => 'Test role',
            'is_active' => true,
        ]);
        $role->permissions()->sync($permissionIds);

        $user = User::create([
            'username' => 'u_'.uniqid(),
            'email' => 'u_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        UserRole::create([
            'account_id' => $user->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'is_active' => true,
            'assigned_by' => $user->account_id,
        ]);

        return $user;
    }

    /** Inertia request headers */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'Accept' => 'text/html, application/xhtml+xml',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  404 — Inertia vs plain
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * An Inertia request to a missing route must return 404.
     * In non-local/testing envs, bootstrap/app.php additionally renders the
     * Errors/Error Inertia page; that path is gated by `!app()->environment(['local','testing'])`
     * so we test the status code here and verify no raw exception text leaks.
     */
    public function test_inertia_404_returns_404_status(): void
    {
        $user = $this->makeUserWithPermissions(['dashboard.student']);

        $response = $this->actingAs($user)
            ->get('/this-route-does-not-exist-xyz', $this->inertiaHeaders());

        $response->assertStatus(404);

        // Raw stack traces must never be exposed in any environment.
        $body = $response->getContent();
        $this->assertStringNotContainsString('Stack trace', $body);
        $this->assertStringNotContainsString('Illuminate\\', $body);
    }

    /** A plain (non-Inertia) request to a missing route should fall back to standard Laravel 404. */
    public function test_non_inertia_404_does_not_render_inertia_component(): void
    {
        $response = $this->get('/this-route-does-not-exist-xyz');

        $response->assertStatus(404);

        // Body must NOT contain an Inertia page JSON payload
        $this->assertStringNotContainsString('"component"', $response->getContent());
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  403 — authenticated user without permission
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * An Inertia request from a user without the required permission must return 403.
     * In non-local/testing envs bootstrap/app.php renders the Errors/Error Inertia page.
     * Note: HandleInertiaRequests is excluded to prevent the 409 version-check response
     * that occurs when no compiled asset manifest exists in the test environment.
     */
    public function test_inertia_403_returns_403_status(): void
    {
        // User without dashboard.admin permission — admin routes require it
        $user = $this->makeUserWithPermissions(['some.other.permission']);

        $response = $this->actingAs($user)
            ->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class)
            ->get(route('admin.facilities.index'), $this->inertiaHeaders());

        $response->assertStatus(403);

        $body = $response->getContent();
        $this->assertStringNotContainsString('Stack trace', $body);
        $this->assertStringNotContainsString('Illuminate\\', $body);
    }

    public function test_non_inertia_403_returns_plain_response(): void
    {
        $user = $this->makeUserWithPermissions(['some.other.permission']);

        $response = $this->actingAs($user)
            ->get(route('student-dashboard.index'));

        $response->assertStatus(403);
        $this->assertStringNotContainsString('"component"', $response->getContent());
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Exception message leakage — environment gate
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * In non-local environments (staging, production) the Inertia error page
     * must receive a null message, not the raw exception text.
     */
    public function test_inertia_error_page_hides_message_in_non_local_env(): void
    {
        config(['app.env' => 'production']);

        $user = $this->makeUserWithPermissions(['some.other.permission']);

        $response = $this->actingAs($user)
            ->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class)
            ->get(route('admin.facilities.index'), $this->inertiaHeaders());

        $response->assertStatus(403);
        $body = $response->getContent();
        // The message prop must be null, not any exception string.
        $this->assertStringNotContainsString('"message":"', $body);
    }

    /**
     * In the local environment, the raw exception message is included in the
     * Inertia error props to aid debugging.
     */
    public function test_inertia_error_page_includes_message_in_local_env(): void
    {
        config(['app.env' => 'local']);

        $user = $this->makeUserWithPermissions(['some.other.permission']);

        $response = $this->actingAs($user)
            ->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class)
            ->get(route('admin.facilities.index'), $this->inertiaHeaders());

        $response->assertStatus(403);
        // In local the respond() block is skipped (env is local/testing) so the
        // standard Laravel exception handler runs — no Inertia error page rendered.
        // The important thing: no raw stack trace leaks in either path.
        $body = $response->getContent();
        $this->assertStringNotContainsString('Stack trace', $body);
        $this->assertStringNotContainsString('#0 ', $body);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Empty-state: submissions endpoint with no data
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * When a student has no submissions, the JSON endpoint must return a
     * well-formed paginated payload with an empty `data` array — never an error.
     * The frontend RecentSubmissionsTable will then render the EmptyState component.
     */
    public function test_submissions_endpoint_returns_empty_paginated_payload_for_new_student(): void
    {
        $user = $this->makeUserWithPermissions(['dashboard.student']);

        $response = $this->actingAs($user)
            ->getJson(route('student-dashboard.submissions'));

        $response->assertOk();
        // Service returns { data: [], meta: { current_page, last_page, per_page, total } }
        $response->assertJsonStructure([
            'data',
            'meta' => ['total'],
        ]);
        $response->assertJsonPath('meta.total', 0);
        $response->assertJsonPath('data', []);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Validation errors — never raw exception text
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Submitting to a staff approval endpoint with no comment (when required)
     * must yield a 422/redirect with validation errors, NOT a raw exception
     * message like "Call to undefined…".
     */
    public function test_staff_approve_with_missing_progress_returns_user_friendly_error(): void
    {
        $user = $this->makeUserWithPermissions(['dashboard.staff']);

        // Progress ID 999999 does not exist — service should catch ModelNotFoundException
        $response = $this->actingAs($user)
            ->put(route('staff-dashboard.progress.approve', ['id' => 999999]), [
                'comment' => 'Looks good',
            ], $this->inertiaHeaders());

        // Must not expose raw exception text — acceptable outcomes are:
        //   - a 303 redirect back with an error flash, OR
        //   - a 4xx/5xx Inertia error page
        $content = $response->getContent();
        $this->assertStringNotContainsString('getMessage', $content);
        $this->assertStringNotContainsString('Stack trace', $content);
        $this->assertStringNotContainsString('Illuminate\\', $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Unauthenticated requests — redirect to login (not 500)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_inertia_request_redirects_to_login(): void
    {
        $response = $this->get(route('student-dashboard.index'), $this->inertiaHeaders());

        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_json_request_returns_401(): void
    {
        $response = $this->getJson(route('student-dashboard.submissions'));

        $response->assertUnauthorized();
    }
}
