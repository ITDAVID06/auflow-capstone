<?php

namespace Tests\Unit;

use App\Modules\UserManagement\Models\User;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\VerificationSnapshot\Services\SnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SnapshotServiceIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_create_or_get_snapshot_returns_existing_snapshot_for_same_action_hash(): void
    {
        $user = User::create([
            'username' => 'snapshot_user_'.uniqid(),
            'email' => 'snapshot_user_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $service = app(SnapshotService::class);

        $attributes = [
            'public_id' => str_repeat('x', 32),
            'submission_id' => 10,
            'form_id' => 20,
            'workflow_id' => 30,
            'step_id' => 40,
            'workflow_step' => 'Department Head Approval',
            'status' => 'Approved',
            'approved_by' => $user->account_id,
            'approved_at' => now(),
            'comment' => 'Looks good',
            'payload_json' => ['a' => 1],
            'action_hash' => hash('sha256', 'same-action-hash'),
            'locked' => true,
            'created_at' => now(),
        ];

        $first = $service->createOrGetSnapshot($attributes);

        $attributes['public_id'] = str_repeat('y', 32);
        $second = $service->createOrGetSnapshot($attributes);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Snapshot::query()->count());
    }
}
