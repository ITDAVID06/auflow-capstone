<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /** Routes the user may access even with must_change_password = true. */
    private const ALLOWED_ROUTES = ['password.change', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, strict: true)) {
            return redirect()->route('password.change');
        }

        $response = $next($request);

        if ($user) {
            // Share flag with Inertia so frontend knows
            inertia()->share([
                'auth' => [
                    'user' => array_merge(
                        $user->only(['account_id', 'username', 'email']),
                        ['must_change_password' => (bool) $user->must_change_password]
                    ),
                ],
            ]);
        }

        return $response;
    }
}
