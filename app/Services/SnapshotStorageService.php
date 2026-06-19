<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Stores and retrieves snapshot rendered-HTML blobs on object storage.
 *
 * Configure the disk via SNAPSHOT_STORAGE_DISK (defaults to "s3") and supply
 * the standard AWS_* credentials when using S3.
 *
 * Usage:
 *   $path = $service->store($snapshot->public_id, $html);
 *   $html = $service->retrieve($path);
 */
class SnapshotStorageService
{
    private string $disk;

    public function __construct()
    {
        $this->disk = (string) config('filesystems.snapshot_disk', 's3');
    }

    /**
     * Upload rendered HTML to object storage under snapshots/{publicId}.html.
     *
     * @return string The object storage path that was written.
     */
    public function store(string $publicId, string $html): string
    {
        $path = "snapshots/{$publicId}.html";

        Storage::disk($this->disk)->put($path, $html, [
            'ContentType' => 'text/html; charset=utf-8',
            'visibility' => 'private',
        ]);

        return $path;
    }

    /**
     * Fetch rendered HTML from object storage.
     */
    public function retrieve(string $path): string
    {
        return (string) Storage::disk($this->disk)->get($path);
    }

    /**
     * Return a temporary pre-signed URL for direct browser access (S3 / compatible).
     *
     * Falls back to an application-proxied URL on disks that do not support
     * temporary URLs (e.g. local).
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiry): string
    {
        try {
            return Storage::disk($this->disk)->temporaryUrl($path, $expiry);
        } catch (\RuntimeException) {
            // Local disk or drivers that do not support temporaryUrl.
            return Storage::disk($this->disk)->url($path);
        }
    }
}
