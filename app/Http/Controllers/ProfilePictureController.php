<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePictureController extends Controller
{
    public function show(string $path): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $normalizedPath = ltrim(str_replace('\\', '/', urldecode($path)), '/');

        // Block path traversal attempts.
        if ($normalizedPath === '' || str_contains($normalizedPath, '../')) {
            abort(404);
        }

        // Serve from the private profile-pictures disk (new uploads).
        if (Storage::disk('profile-pictures')->exists($normalizedPath)) {
            return Storage::disk('profile-pictures')->download(
                $normalizedPath,
                basename($normalizedPath),
                ['Content-Disposition' => 'inline; filename="'.basename($normalizedPath).'"']
            );
        }

        // Backward compatibility: if the file only exists on the public disk,
        // redirect to the public storage URL so legacy files continue to work.
        if (Storage::disk('public')->exists($normalizedPath)) {
            return redirect('/storage/'.$normalizedPath);
        }

        abort(404);
    }
}
