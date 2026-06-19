<?php

namespace App\Events;

use App\Modules\VerificationSnapshot\Models\Snapshot;

class SnapshotVerified
{
    // $result: 'Verified' | 'Mismatch' | 'Notfound'
    public function __construct(public Snapshot $snapshot, public string $result, public ?string $payload = null) {}
}
