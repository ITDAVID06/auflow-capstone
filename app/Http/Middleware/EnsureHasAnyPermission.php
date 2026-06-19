<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureHasAnyPermission
{
    /**
     * Usage examples:
     *   any-permission:Manage Users
     *   any-permission:Manage Users,Manage Users (Org)
     */
    public function handle(Request $request, Closure $next, string ...$perms)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        // Flatten params: ["A", "B,C"] -> ["A","B","C"]
        $need = [];
        foreach ($perms as $p) {
            foreach (explode(',', $p) as $s) {
                $s = trim($s);
                if ($s !== '') {
                    $need[] = mb_strtolower($s);
                }
            }
        }

        // Normalize what the user has
        $have = array_map(fn ($s) => mb_strtolower(trim($s)), $user->allPermissions());

        foreach ($need as $perm) {
            if (in_array($perm, $have, true)) {
                return $next($request);
            }
        }

        abort(403, 'Forbidden');
    }
}
