<?php

namespace App\Modules\VerificationSnapshot\Observers;

use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Log;

class SnapshotObserver
{
    public function __construct(private AuditLogger $audit) {}

    public function created(Snapshot $m): void
    {
        $name = $this->nameOf($m);
        $this->audit->userAction('snapshot_created', $m, 'Success', "Created snapshot {$name}", [
            'snapshot_id' => $m->getKey(),
            'snapshot_name' => $name,
        ]);
    }

    public function updated(Snapshot $m): void
    {
        Log::critical('[Snapshot] Attempted update on immutable snapshot record — DB trigger should have blocked this.', [
            'snapshot_id' => $m->getKey(),
            'public_id' => $m->public_id,
            'locked' => $m->locked,
        ]);
    }

    public function deleted(Snapshot $m): void
    {
        $name = $this->nameOf($m);
        $this->audit->userAction('snapshot_deleted', $m, 'Warning', "Deleted snapshot {$name}", [
            'snapshot_id' => $m->getKey(),
            'snapshot_name' => $name,
        ]);
    }

    private function nameOf(Snapshot $m): string
    {
        return $m->snapshot_name ?? $m->public_id ?? "Snapshot #{$m->getKey()}";
    }
}
