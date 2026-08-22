<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops a suspended account from using the app while keeping every one of its
 * highlights intact (Constitution art. III: data is never deleted).
 *
 * The session is torn down rather than merely refused, so a suspension takes
 * effect on the next request instead of lingering until the cookie expires.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('errors.suspended.body'),
            ]);
        }

        return $next($request);
    }
}
