<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

class RemovePayloadEncryption extends Command
{
    protected $signature = 'keys:remove-payload-encryption {--dry-run : Preview what will happen without making changes}';

    protected $description = 'Decrypt all payload_json values in tbl_form_submission and store them as plain JSON.';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            return $this->runDryRun();
        }

        return $this->runLive();
    }

    private function encrypter(): Encrypter
    {
        $raw = env('PAYLOAD_ENCRYPTION_KEY', '');

        if (empty($raw)) {
            throw new \RuntimeException('PAYLOAD_ENCRYPTION_KEY is not set in the environment.');
        }

        if (str_starts_with($raw, 'base64:')) {
            $raw = base64_decode(substr($raw, 7));
        }

        return new Encrypter($raw, 'AES-256-CBC');
    }

    /**
     * Recursively decrypt any `['__encrypted' => true, 'data' => '<ciphertext>']` nodes.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function decryptPayload(array $payload, Encrypter $encrypter): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                if (isset($value['__encrypted']) && $value['__encrypted'] === true && isset($value['data'])) {
                    try {
                        $payload[$key] = $encrypter->decryptString($value['data']);
                    } catch (\Throwable) {
                        $payload[$key] = '[DECRYPTION_FAILED]';
                    }
                } else {
                    $payload[$key] = $this->decryptPayload($value, $encrypter);
                }
            }
        }

        return $payload;
    }

    private function runDryRun(): int
    {
        $this->info('[DRY RUN] No changes will be written to the database.');

        $count = DB::table('tbl_form_submission')
            ->whereNotNull('payload_json')
            ->where('payload_json', '!=', '')
            ->count();

        $this->line("Rows with a non-empty payload_json: {$count}");

        if ($count === 0) {
            $this->info('Nothing to migrate.');

            return self::SUCCESS;
        }

        // Attempt to decrypt one sample row to verify the key works.
        $sample = DB::table('tbl_form_submission')
            ->whereNotNull('payload_json')
            ->where('payload_json', '!=', '')
            ->first();

        $decoded = json_decode($sample->payload_json, true);

        if (! is_array($decoded)) {
            $this->error('Sample row payload_json could not be JSON-decoded. Aborting.');

            return self::FAILURE;
        }

        try {
            $encrypter = $this->encrypter();
            $decrypted = $this->decryptPayload($decoded, $encrypter);
        } catch (\Throwable $e) {
            $this->error('Decryption of sample row failed: '.$e->getMessage());
            $this->warn('Ensure PAYLOAD_ENCRYPTION_KEY in your environment matches the key used when data was encrypted.');

            return self::FAILURE;
        }

        if (array_search('[DECRYPTION_FAILED]', $decrypted, true) !== false) {
            $this->error('At least one field in the sample row could not be decrypted. Aborting.');
            $this->warn('Ensure PAYLOAD_ENCRYPTION_KEY in your environment matches the key used when data was encrypted.');

            return self::FAILURE;
        }

        $preview = substr(json_encode($decrypted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 120);
        $this->line("Sample row (first 120 chars of decrypted payload): {$preview}");
        $this->info("Dry run complete. Run without --dry-run to migrate {$count} row(s).");

        return self::SUCCESS;
    }

    private function runLive(): int
    {
        $this->info('Starting live decryption. This cannot be undone — ensure you have a database backup.');

        try {
            $encrypter = $this->encrypter();
        } catch (\RuntimeException $e) {
            $this->warn($e->getMessage().' (Assuming all rows are already plain JSON.)');
            $encrypter = null;
        }

        $decrypted = 0;
        $skipped = 0;

        DB::table('tbl_form_submission')
            ->whereNotNull('payload_json')
            ->where('payload_json', '!=', '')
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($encrypter, &$decrypted, &$skipped): void {
                foreach ($rows as $row) {
                    $decoded = json_decode($row->payload_json, true);

                    if (! is_array($decoded)) {
                        $skipped++;

                        continue;
                    }

                    if (! $this->hasEncryptedFields($decoded)) {
                        $skipped++;

                        continue;
                    }

                    if ($encrypter === null) {
                        $skipped++;

                        continue;
                    }

                    $plain = $this->decryptPayload($decoded, $encrypter);

                    DB::table('tbl_form_submission')
                        ->where('id', $row->id)
                        ->update([
                            'payload_json' => json_encode($plain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);

                    $decrypted++;
                }
            });

        $this->info("Decrypted: {$decrypted} row(s). Skipped (already plain or unreadable): {$skipped} row(s).");

        // Final verification: spot-check up to 5 random rows.
        $this->info('Running final verification on a random sample…');

        $samples = DB::table('tbl_form_submission')
            ->whereNotNull('payload_json')
            ->where('payload_json', '!=', '')
            ->inRandomOrder()
            ->limit(5)
            ->get();

        $failures = 0;

        foreach ($samples as $sample) {
            $data = json_decode($sample->payload_json, true);

            if (! is_array($data)) {
                $this->error("  Row ID {$sample->id}: payload_json is not a valid JSON object.");
                $failures++;

                continue;
            }

            if ($this->hasEncryptedFields($data)) {
                $this->error("  Row ID {$sample->id}: payload_json still contains encrypted fields.");
                $failures++;

                continue;
            }

            $this->line("  Row ID {$sample->id}: OK (plain JSON)");
        }

        if ($failures > 0) {
            $this->error("Verification failed on {$failures} sample(s). Investigate before removing the encryption key.");

            return self::FAILURE;
        }

        $this->info('All sampled rows verified as plain JSON. Migration complete.');

        return self::SUCCESS;
    }

    /**
     * Recursively check whether a decoded payload contains any encrypted fields.
     *
     * @param  array<string, mixed>  $payload
     */
    private function hasEncryptedFields(array $payload): bool
    {
        foreach ($payload as $value) {
            if (is_array($value)) {
                if (isset($value['__encrypted']) && $value['__encrypted'] === true) {
                    return true;
                }

                if ($this->hasEncryptedFields($value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
