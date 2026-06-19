<?php

namespace App\Modules\VerificationSnapshot\Services;

use App\Modules\VerificationSnapshot\Models\Snapshot;

class SnapshotSecurityService
{
    /**
     * Generate action hash from actor ID, timestamp, and payload
     *
     * Formula: SHA256(actor_id|timestamp|payload_json)
     *
     * @param  int  $actorId  The account_id of the person taking action
     * @param  int  $timestamp  Unix timestamp of the action
     * @param  array  $payload  The complete snapshot payload
     * @return string 64-character SHA-256 hash
     */
    public function generateActionHash(int $actorId, int $timestamp, array $payload): string
    {
        // Normalize JSON to ensure consistent hashing
        $normalizedPayload = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        // Combine actor ID, timestamp, and payload using delimiter
        $hashInput = $actorId.'|'.$timestamp.'|'.$normalizedPayload;

        // Generate signed HMAC hash
        return hash_hmac('sha256', $hashInput, $this->signingKey());
    }

    /**
     * Verify a snapshot's action hash
     *
     * @return array ['valid' => bool, 'expected' => string, 'actual' => string, ...]
     */
    public function verifyActionHash(Snapshot $snapshot): array
    {
        // If no hash stored, it's invalid
        if (empty($snapshot->action_hash)) {
            return [
                'valid' => false,
                'error' => 'No action hash found in snapshot',
                'snapshot_id' => $snapshot->id,
                'public_id' => $snapshot->public_id,
            ];
        }

        // Get actor_id (check both old and new structure)
        $actorId = $snapshot->approved_by ?? $snapshot->rejected_by;

        // Get timestamp - handle both string and Carbon instances
        $timestamp = $snapshot->approved_at
            ? (is_string($snapshot->approved_at) ? strtotime($snapshot->approved_at) : $snapshot->approved_at->timestamp)
            : (is_string($snapshot->created_at) ? strtotime($snapshot->created_at) : $snapshot->created_at->timestamp);

        // Recompute the expected hash
        $expectedHmacHash = $this->generateActionHash(
            $actorId,
            $timestamp,
            $snapshot->payload_json
        );

        // Timing-safe comparison to prevent timing attacks
        $isHmacValid = hash_equals($expectedHmacHash, $snapshot->action_hash);

        $expectedLegacyHash = hash('sha256', $actorId.'|'.$timestamp.'|'.json_encode(
            $snapshot->payload_json,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
        $isLegacyValid = (bool) config('workflow.snapshot.allow_legacy_hash_verification', true)
            && hash_equals($expectedLegacyHash, $snapshot->action_hash);

        $isValid = $isHmacValid || $isLegacyValid;
        $matchedAlgorithm = $isHmacValid
            ? 'hmac-sha256'
            : ($isLegacyValid ? 'legacy-sha256' : 'none');

        return [
            'valid' => $isValid,
            'expected' => $expectedHmacHash,
            'actual' => $snapshot->action_hash,
            'algorithm' => $matchedAlgorithm,
            'actor_id' => $actorId,
            'timestamp' => $timestamp,
            'snapshot_id' => $snapshot->id,
            'public_id' => $snapshot->public_id,
            'message' => $isValid
                ? 'Snapshot is authentic and has not been tampered with'
                : 'WARNING: Snapshot may have been tampered with - hash mismatch',
        ];
    }

    private function signingKey(): string
    {
        return (string) config('workflow.snapshot.signing_key', config('app.key', ''));
    }

    /**
     * Verify all snapshots for a submission (complete audit trail)
     */
    public function verifySubmissionSnapshots(int $submissionId): array
    {
        $snapshots = Snapshot::where('submission_id', $submissionId)
            ->with('approver.profile')
            ->orderBy('created_at')
            ->get();

        if ($snapshots->isEmpty()) {
            return [
                'submission_id' => $submissionId,
                'all_valid' => false,
                'total_snapshots' => 0,
                'error' => 'No snapshots found for this submission',
                'steps' => [],
            ];
        }

        $results = [];
        $allValid = true;

        foreach ($snapshots as $snapshot) {
            $verification = $this->verifyActionHash($snapshot);

            $results[] = [
                'step' => $snapshot->workflow_step,
                'actor' => $snapshot->approver->full_name ?? $snapshot->approver->username ?? 'Unknown',
                'actor_id' => $snapshot->approved_by,
                'status' => $snapshot->status,
                'valid' => $verification['valid'],
                'timestamp' => $snapshot->approved_at->toIso8601String(),
                'public_id' => $snapshot->public_id,
            ];

            if (! $verification['valid']) {
                $allValid = false;
            }
        }

        return [
            'submission_id' => $submissionId,
            'all_valid' => $allValid,
            'total_snapshots' => count($results),
            'steps' => $results,
            'message' => $allValid
                ? 'All snapshots are valid - complete audit trail verified'
                : 'One or more snapshots failed verification - possible tampering detected',
        ];
    }

    /**
     * Get hash algorithm info
     */
    public function getHashInfo(): array
    {
        return [
            'algorithm' => 'SHA-256',
            'output_length' => 64,
            'format' => 'hexadecimal',
            'components' => [
                'actor_id' => 'Account ID of the person who took action',
                'timestamp' => 'Unix timestamp when the action was taken',
                'payload' => 'Complete JSON payload of the snapshot',
            ],
            'formula' => 'SHA256(actor_id|timestamp|payload_json)',
        ];
    }
}
