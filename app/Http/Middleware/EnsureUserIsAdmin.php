<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /admin.
 *
 * 403 rather than 404 here, unlike the ownership checks elsewhere: the panel's
 * existence is not a secret, and a signed-in non-admin deserves a straight
 * answer (FR-069, contracts/routes.md "Hata sözleşmesi").
 */
final class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            abort(403, __('errors.forbidden.body'));
        }

        return $next($request);
    }
}
