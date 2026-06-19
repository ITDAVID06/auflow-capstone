<?php

namespace App\Services\DemoSeeding;

use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Support\Facades\DB;

class DemoAuditSeeder
{
    public function seed(): int
    {
        DB::table('tbl_audit_log')
            ->where('metadata', 'like', '%seed:demo:%')
            ->delete();

        $now = now();
        $rows = [];

        $submissions = FormSubmission::query()
            ->orderBy('id')
            ->get(['id', 'account_id', 'submission_status', 'current_workflow_status']);

        foreach ($submissions as $submission) {
            $rows[] = [
                'category' => 'system_event',
                'action' => 'seed_submission',
                'status' => strtolower((string) $submission->current_workflow_status),
                'description' => 'Demo submission seeded for walkthrough coverage.',
                'actor_id' => (int) $submission->account_id,
                'actor_name' => 'Demo Seeder',
                'actor_email' => 'system@auf.test',
                'actor_role' => 'System',
                'auditable_type' => FormSubmission::class,
                'auditable_id' => (int) $submission->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'seed:demo',
                'snapshot_id' => null,
                'snapshot_public_id' => null,
                'qr_payload' => null,
                'qr_image_path' => null,
                'verification_result' => null,
                'metadata' => json_encode([
                    'seed_key' => "seed:demo:submission:{$submission->id}",
                    'submission_status' => $submission->submission_status,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $overrideProgresses = WorkflowStepProgress::query()
            ->where('action_taken', 'Override-Approve')
            ->orderBy('id')
            ->get(['id', 'submission_id', 'actor_id']);

        foreach ($overrideProgresses as $progress) {
            $rows[] = [
                'category' => 'user_action',
                'action' => 'override_approval',
                'status' => 'approved',
                'description' => 'Admin override approval seeded for demo.',
                'actor_id' => (int) $progress->actor_id,
                'actor_name' => 'Demo Admin',
                'actor_email' => 'admin@auf.test',
                'actor_role' => 'Admin',
                'auditable_type' => FormSubmission::class,
                'auditable_id' => (int) $progress->submission_id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'seed:demo',
                'snapshot_id' => null,
                'snapshot_public_id' => null,
                'qr_payload' => null,
                'qr_image_path' => null,
                'verification_result' => null,
                'metadata' => json_encode([
                    'seed_key' => "seed:demo:override:{$progress->id}",
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('tbl_audit_log')->insert($chunk);
        }

        return count($rows);
    }
}
