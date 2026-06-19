<?php

namespace Tests\Unit;

use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\VerificationSnapshot\Services\SnapshotSecurityService;
use Carbon\Carbon;
use Tests\TestCase;

class SnapshotSecurityServiceTest extends TestCase
{
    public function test_generates_and_verifies_hmac_action_hash(): void
    {
        config()->set('workflow.snapshot.signing_key', 'snapshot-test-key');
        config()->set('workflow.snapshot.allow_legacy_hash_verification', true);

        $service = new SnapshotSecurityService;
        $payload = ['form' => ['id' => 1], 'approval' => ['status' => 'Approved']];
        $timestamp = Carbon::parse('2026-02-14 10:00:00')->timestamp;
        $hash = $service->generateActionHash(123, $timestamp, $payload);

        $snapshot = new Snapshot([
            'id' => 1,
            'public_id' => str_repeat('a', 32),
            'approved_by' => 123,
            'approved_at' => Carbon::createFromTimestamp($timestamp),
            'payload_json' => $payload,
            'action_hash' => $hash,
            'created_at' => now(),
        ]);

        $result = $service->verifyActionHash($snapshot);

        $this->assertTrue($result['valid']);
        $this->assertSame('hmac-sha256', $result['algorithm']);
    }

    public function test_accepts_legacy_hash_when_enabled(): void
    {
        config()->set('workflow.snapshot.signing_key', 'snapshot-test-key');
        config()->set('workflow.snapshot.allow_legacy_hash_verification', true);

        $service = new SnapshotSecurityService;
        $payload = ['form' => ['id' => 2], 'approval' => ['status' => 'Rejected']];
        $timestamp = Carbon::parse('2026-02-14 10:00:00')->timestamp;
        $legacyHash = hash(
            'sha256',
            '456|'.$timestamp.'|'.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $snapshot = new Snapshot([
            'id' => 2,
            'public_id' => str_repeat('b', 32),
            'approved_by' => 456,
            'approved_at' => Carbon::createFromTimestamp($timestamp),
            'payload_json' => $payload,
            'action_hash' => $legacyHash,
            'created_at' => now(),
        ]);

        $result = $service->verifyActionHash($snapshot);

        $this->assertTrue($result['valid']);
        $this->assertSame('legacy-sha256', $result['algorithm']);
    }

    public function test_rejects_legacy_hash_when_legacy_verification_disabled(): void
    {
        config()->set('workflow.snapshot.signing_key', 'snapshot-test-key');
        config()->set('workflow.snapshot.allow_legacy_hash_verification', false);

        $service = new SnapshotSecurityService;
        $payload = ['form' => ['id' => 3], 'approval' => ['status' => 'Approved']];
        $timestamp = Carbon::parse('2026-02-14 10:00:00')->timestamp;
        $legacyHash = hash(
            'sha256',
            '789|'.$timestamp.'|'.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $snapshot = new Snapshot([
            'id' => 3,
            'public_id' => str_repeat('c', 32),
            'approved_by' => 789,
            'approved_at' => Carbon::createFromTimestamp($timestamp),
            'payload_json' => $payload,
            'action_hash' => $legacyHash,
            'created_at' => now(),
        ]);

        $result = $service->verifyActionHash($snapshot);

        $this->assertFalse($result['valid']);
        $this->assertSame('none', $result['algorithm']);
    }
}
