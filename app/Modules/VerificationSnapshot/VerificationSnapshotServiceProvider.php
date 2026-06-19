<?php

namespace App\Modules\VerificationSnapshot;

use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\VerificationSnapshot\Observers\SnapshotObserver;
use Illuminate\Support\ServiceProvider;

/**
 * VerificationSnapshot Module Service Provider
 *
 * Manages immutable verification snapshots of approved/rejected
 * submissions with QR codes and short codes for public verification.
 *
 * @dependencies
 *  - AuditTrail: AuditLogger service for lifecycle audit logging
 *  - WorkflowBuilder: Snapshots created on approval/rejection via
 *    WorkflowStepProgressObserver; linked to workflow progress records
 *  - FormBuilder: Snapshots reference form submissions and field data
 *  - UserManagement: Snapshot actors reference user accounts
 */
class VerificationSnapshotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Snapshot::observe(SnapshotObserver::class);
    }
}
