<?php

namespace App\Http\Controllers\Settings;

use App\Actions\UserManagement\UpdateUserProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProfileRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's editable profile fields (username, name, address).
     */
    public function update(UpdateProfileRequest $request, UpdateUserProfileAction $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated());

        return back()->with('success', 'Profile updated.');
    }

    /**
     * Update the user's profile picture.
     */
    public function updateProfilePicture(Request $request): RedirectResponse
    {
        $request->validate([
            'profile_picture' => ['required', 'image', 'max:2048'], // Max 2MB
        ]);

        $user = $request->user();
        $profile = $user->profile;

        if (! $profile) {
            return back()->with('error', 'Profile not found');
        }

        // Delete old profile picture if exists
        if ($profile->profile_picture) {
            Storage::disk('profile-pictures')->delete($profile->profile_picture);
            // Backward compat: also clean up from public disk if present.
            Storage::disk('public')->delete($profile->profile_picture);
        }

        // Store new profile picture on the private disk
        $path = $request->file('profile_picture')->store('', 'profile-pictures');

        // Update profile
        $profile->update([
            'profile_picture' => $path,
        ]);

        return back()->with('success', 'Profile picture updated successfully');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
