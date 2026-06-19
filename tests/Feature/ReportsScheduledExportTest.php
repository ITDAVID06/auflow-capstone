<?php

namespace Tests\Feature;

use App\Mail\ScheduledExportMail;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\Reports\Models\ScheduledExport;
use App\Modules\Reports\Services\ScheduledExportService;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReportsScheduledExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    // ---------------------------------------------------------------
    // CRUD endpoint tests
    // ---------------------------------------------------------------

    public function test_user_can_create_a_scheduled_export(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $this->actingAs($user)
            ->postJson(route('reports.scheduled-exports.store'), [
                'form_id' => $form->id,
                'recipient_email' => 'export@example.com',
                'frequency' => 'daily',
                'export_type' => 'csv',
                'filter_state' => ['submission_status' => 'approved'],
            ])
            ->assertCreated()
            ->assertJsonFragment(['recipient_email' => 'export@example.com'])
            ->assertJsonFragment(['frequency' => 'daily']);

        $this->assertDatabaseHas('tbl_scheduled_export', [
            'recipient_email' => 'export@example.com',
            'created_by' => $user->account_id,
        ]);
    }

    public function test_user_can_list_their_own_scheduled_exports(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $other = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'mine@example.com',
            'frequency' => 'weekly',
            'export_type' => 'pdf',
            'filter_state' => [],
            'is_active' => true,
            'created_by' => $user->account_id,
        ]);

        // This one belongs to another user — should not be visible.
        ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'theirs@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'created_by' => $other->account_id,
        ]);

        $this->actingAs($user)
            ->getJson(route('reports.scheduled-exports.index'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['recipient_email' => 'mine@example.com']);
    }

    public function test_user_can_update_their_own_scheduled_export(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $export = ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'old@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'created_by' => $user->account_id,
        ]);

        $this->actingAs($user)
            ->putJson(route('reports.scheduled-exports.update', $export->id), [
                'recipient_email' => 'new@example.com',
                'frequency' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonFragment(['recipient_email' => 'new@example.com'])
            ->assertJsonFragment(['frequency' => 'monthly']);
    }

    public function test_user_cannot_update_another_users_scheduled_export(): void
    {
        $owner = $this->createUserWithPermissions(['submissions.view']);
        $other = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($owner);

        $export = ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'owner@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'created_by' => $owner->account_id,
        ]);

        $this->actingAs($other)
            ->putJson(route('reports.scheduled-exports.update', $export->id), [
                'frequency' => 'weekly',
            ])
            ->assertStatus(403);
    }

    public function test_user_can_delete_their_own_scheduled_export(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $export = ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'del@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'created_by' => $user->account_id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('reports.scheduled-exports.destroy', $export->id))
            ->assertNoContent();

        $this->assertDatabaseMissing('tbl_scheduled_export', ['id' => $export->id]);
    }

    public function test_user_cannot_delete_another_users_scheduled_export(): void
    {
        $owner = $this->createUserWithPermissions(['submissions.view']);
        $other = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($owner);

        $export = ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'keep@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'created_by' => $owner->account_id,
        ]);

        $this->actingAs($other)
            ->deleteJson(route('reports.scheduled-exports.destroy', $export->id))
            ->assertStatus(403);

        $this->assertDatabaseHas('tbl_scheduled_export', ['id' => $export->id]);
    }

    // ---------------------------------------------------------------
    // findDue() logic tests
    // ---------------------------------------------------------------

    public function test_find_due_returns_never_sent_active_exports(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $neverSent = ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'a@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'last_sent_at' => null,
            'created_by' => $user->account_id,
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertTrue($due->contains('id', $neverSent->id));
    }

    public function test_find_due_returns_daily_export_sent_over_24h_ago(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $past = ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'b@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'last_sent_at' => now()->subHours(25),
            'created_by' => $user->account_id,
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertTrue($due->contains('id', $past->id));
    }

    public function test_find_due_excludes_daily_export_sent_recently(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $recent = ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'c@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'last_sent_at' => now()->subHour(),
            'created_by' => $user->account_id,
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertFalse($due->contains('id', $recent->id));
    }

    public function test_find_due_excludes_inactive_exports(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $inactive = ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'd@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => false,
            'last_sent_at' => null,
            'created_by' => $user->account_id,
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertFalse($due->contains('id', $inactive->id));
    }

    public function test_find_due_returns_weekly_export_sent_over_7_days_ago(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $weekly = ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'e@example.com',
            'frequency' => 'weekly',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'last_sent_at' => now()->subDays(8),
            'created_by' => $user->account_id,
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertTrue($due->contains('id', $weekly->id));
    }

    public function test_find_due_excludes_weekly_export_sent_recently(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $recent = ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'f@example.com',
            'frequency' => 'weekly',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'last_sent_at' => now()->subDays(3),
            'created_by' => $user->account_id,
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertFalse($due->contains('id', $recent->id));
    }

    // ---------------------------------------------------------------
    // Artisan command tests
    // ---------------------------------------------------------------

    public function test_command_sends_mail_and_updates_last_sent_at(): void
    {
        Mail::fake();

        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $export = ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'cmd@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'last_sent_at' => null,
            'created_by' => $user->account_id,
        ]);

        $this->artisan('reports:send-scheduled-exports')->assertSuccessful();

        Mail::assertSent(ScheduledExportMail::class, function (ScheduledExportMail $mail) use ($export) {
            return $mail->hasTo($export->recipient_email);
        });

        $this->assertNotNull($export->fresh()->last_sent_at);
    }

    public function test_command_does_not_resend_before_threshold(): void
    {
        Mail::fake();

        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        ScheduledExport::create([
            'form_id' => $form->id,
            'recipient_email' => 'skip@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'filter_state' => [],
            'is_active' => true,
            'last_sent_at' => now()->subMinutes(30),
            'created_by' => $user->account_id,
        ]);

        $this->artisan('reports:send-scheduled-exports')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_command_outputs_no_due_message_when_queue_is_empty(): void
    {
        $this->artisan('reports:send-scheduled-exports')
            ->assertSuccessful()
            ->expectsOutputToContain('No scheduled exports are due.');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createUserWithPermissions(array $slugs): User
    {
        $ids = [];
        foreach ($slugs as $slug) {
            $ids[] = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'permission_name' => ucwords(str_replace(['.', '-'], ' ', $slug)),
                    'description' => 'Test',
                    'resource' => explode('.', $slug)[0] ?? 'test',
                    'action' => explode('.', $slug)[1] ?? 'access',
                ]
            )->id;
        }

        $role = Role::create(['role_name' => 'Role '.uniqid(), 'description' => 'Test', 'is_active' => true]);
        $role->permissions()->sync($ids);

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

    private function createReportForm(User $creator): Form
    {
        $form = Form::create([
            'form_name' => 'Form '.uniqid(),
            'form_code' => 'F'.uniqid(),
            'description' => 'Test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_text_name',
            'label' => 'Name',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        return $form;
    }
}
