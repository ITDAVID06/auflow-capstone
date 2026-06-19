<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordChangeController extends Controller
{
    public function showForm()
    {
        return inertia('auth/ChangePasswordPage');
    }

    public function update(ChangePasswordRequest $request)
    {
        $user = $request->user();

        // Check current password
        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'Your current password does not match our records.',
            ]);
        }

        // Update to new password
        $user->password = Hash::make($request->password);
        $user->must_change_password = false; // allow login without forced reset
        $user->save();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Password updated successfully.');
    }
}
