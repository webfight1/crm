<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnforceOutreachOnly
 *
 * Defence-in-depth for OUTREACH_ONLY_MODE: even with the navigation
 * hiding non-outreach modules, a user with a known URL could still type
 * /customers or /deals directly. This middleware rejects those routes
 * with a redirect to the outreach dashboard when the mode is enabled,
 * so the deployment is truly limited to the outreach feature surface.
 *
 * Allow-list (paths that remain accessible):
 *   - /outreach/*           — the feature itself
 *   - /login, /logout       — auth
 *   - /profile, /password   — Breeze account management
 *   - /settings/*           — app-level settings the operator may need
 *   - /time-entries/current — referenced by the navbar timer poll
 *   - /up                   — health check
 *   - /                     — bare root (redirected to dashboard elsewhere)
 *
 * When the mode is disabled (default), the middleware is a no-op so the
 * full CRM remains accessible.
 */
class EnforceOutreachOnly
{
    /** @var string[] Allowed top-level path prefixes when outreach-only mode is on. */
    private const ALLOWED_PREFIXES = [
        'outreach',
        'login',
        'logout',
        'profile',
        'password',
        'settings',
        'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.outreach_only')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        // Bare root: let the dashboard route handle / redirect.
        if ($path === '' || $path === '/') {
            return $next($request);
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $next($request);
            }
        }

        // For non-allowed paths: bounce authenticated users to outreach,
        // unauthenticated to login. Avoids leaking which modules exist.
        if ($request->user()) {
            return redirect()->route('outreach.dashboard');
        }
        return redirect()->route('login');
    }
}
