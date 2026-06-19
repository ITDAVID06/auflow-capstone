<?php

namespace App\Services;

class ProfilePictureUrlResolver
{
    public function resolve(?string $profilePicturePath): ?string
    {
        if (! is_string($profilePicturePath)) {
            return null;
        }

        $normalizedPath = trim($profilePicturePath);
        if ($normalizedPath === '') {
            return null;
        }

        // Absolute URLs and root-relative legacy /storage/... URLs pass through unchanged.
        if (
            str_starts_with($normalizedPath, 'http://')
            || str_starts_with($normalizedPath, 'https://')
            || str_starts_with($normalizedPath, '/')
        ) {
            return $normalizedPath;
        }

        // Relative paths are served through the authenticated profile-pictures route.
        return '/profile-pictures/'.ltrim($normalizedPath, '/');
    }
}
