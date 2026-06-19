<?php

namespace App\Services;

use App\Modules\AuditTrail\Models\AuditLog;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public function __construct(private Request $request, private Guard $auth) {}

    // app/Services/AuditLogger.php
    public function log(array $data): AuditLog
    {
        $user = $this->auth->user();

        // Resolve actor basics
        $actorId = $data['actor_id'] ?? ($user->account_id ?? $user->id ?? null);
        $actorName = $data['actor_name'] ?? ($user?->full_name ?? $user?->name ?? $user?->username ?? null);
        $actorEmail = $data['actor_email'] ?? ($user?->email ?? null);

        // Resolve active role name from your pivot
        $roleName = $data['actor_role'] ?? null;
        if (! $roleName && $actorId) {
            $roleName = DB::table('tbl_user_role as ur')
                ->join('tbl_role as r', 'r.id', '=', 'ur.role_id')
                ->where('ur.account_id', $actorId)
                ->where('ur.is_active', 1)
                ->orderByDesc('ur.assigned_date')
                ->value('r.role_name');
        }

        return AuditLog::create([
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'actor_email' => $actorEmail,
            'actor_role' => $roleName,

            'category' => $data['category'],
            'action' => $data['action'],
            'status' => $data['status'] ?? null,
            'description' => $data['description'] ?? null,

            'auditable_type' => $data['auditable_type'] ?? null,
            'auditable_id' => $data['auditable_id'] ?? null,

            'ip_address' => $data['ip_address'] ?? $this->request->ip(),
            'user_agent' => $data['user_agent'] ?? substr((string) $this->request->header('User-Agent'), 0, 1000),

            'snapshot_id' => $data['snapshot_id'] ?? null,
            'snapshot_public_id' => $data['snapshot_public_id'] ?? null,
            'qr_payload' => $data['qr_payload'] ?? null,
            'qr_image_path' => $data['qr_image_path'] ?? null,
            'verification_result' => $data['verification_result'] ?? null,

            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    public function userAction(string $action, $auditable = null, ?string $status = null, ?string $description = null, array $meta = []): AuditLog
    {
        return $this->log([
            'category' => 'user_action', 'action' => $action, 'status' => $status, 'description' => $description,
            ...$this->auditable($auditable), 'metadata' => $meta,
        ]);
    }

    // public function systemEvent(string $action, string $status = null, string $description = null, array $meta = []): AuditLog
    // {
    //     return $this->log([
    //         'category' => 'system_event', 'action' => $action, 'status' => $status, 'description' => $description,
    //         'actor_id' => null, 'actor_name' => 'System', 'actor_role' => 'System', 'metadata' => $meta,
    //     ]);
    // }

    public function security(string $action, ?string $status = null, ?string $description = null, array $meta = [], $auditable = null): AuditLog
    {
        return $this->log([
            'category' => 'security', 'action' => $action, 'status' => $status, 'description' => $description,
            ...$this->auditable($auditable), 'metadata' => $meta,
        ]);
    }

    /** Log QR verification (success/mismatch/notfound) */
    public function qrVerification(string $result, array $data = [], $auditable = null): AuditLog
    {
        return $this->log([
            'category' => 'security',
            'action' => 'qr_verification',
            'status' => ucfirst($result), // Verified | Mismatch | Notfound
            'description' => $data['description'] ?? 'QR verification processed',
            ...$this->auditable($auditable),
            'snapshot_id' => $data['snapshot_id'] ?? null,
            'snapshot_public_id' => $data['snapshot_public_id'] ?? null,
            'qr_payload' => $data['qr_payload'] ?? null,
            'qr_image_path' => $data['qr_image_path'] ?? null,
            'verification_result' => ucfirst($result),
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    private function auditable($model): array
    {
        return $model ? ['auditable_type' => get_class($model), 'auditable_id' => $model->getKey()] : [];
    }
}
