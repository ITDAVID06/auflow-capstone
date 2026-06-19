<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        $user = \App\Modules\UserManagement\Models\User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors(['email' => __('auth.failed')]);
        }

        // Prevent inactive accounts from logging in
        if ($user->status?->status_name === 'Inactive') {
            return back()->withErrors(['email' => 'Your account is inactive. Please contact support.']);
        }

        // Prevent archived accounts from logging in
        if ($user->status?->status_name === 'Archive') {
            return back()->withErrors(['email' => 'Your account has been archived. Please contact support.']);
        }

        if (! Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return back()->withErrors(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        // Use the authenticated user context for redirect resolution
        $authenticatedUser = Auth::user();

        // Redirect based on role - don't use intended() to avoid cross-role redirect issues
        if ($authenticatedUser && $authenticatedUser->hasPermission('dashboard.admin')) {
            return redirect('/dashboard');
        }

        if ($authenticatedUser && $authenticatedUser->hasPermission('dashboard.staff')) {
            return redirect('/staff-dashboard');
        }

        if ($authenticatedUser && $authenticatedUser->hasPermission('dashboard.student')) {
            return redirect('/student-dashboard');
        }

        // Final fallback for users without dashboard permissions
        return redirect('/');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
