<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temporary password-only "coming soon" gate for the entire public website
 * (client requirement, 2026-09-09) — completely independent of, and never a
 * replacement for, the real Laravel membership/authentication system
 * (registration/login/Filament admin all still work exactly as before, just
 * behind this one extra check). Applied globally to the `web` middleware
 * group (bootstrap/app.php), same "guard by path exclusion" approach as the
 * existing CheckMaintenanceMode — listed ahead of it (but still appended,
 * after the group's own StartSession: $request->session() below needs that
 * to have already run) so an un-authorized visitor never learns whether the
 * site is also in maintenance mode.
 *
 * Session-only: no database table, no cookie of its own beyond Laravel's
 * existing session cookie. config('beta.*') is env-only (see config/beta.php)
 * so flipping the gate on/off or changing the password is a production
 * .env edit, never a source file.
 *
 * /admin, /login, /register are intentionally NOT excluded — the client
 * explicitly requires the entire site, including those, to sit behind the
 * gate. Once a visitor's session has beta_access_granted = true, every one
 * of those routes works completely normally again.
 *
 * A visitor-uploaded file under /storage/... is served directly by Nginx
 * (public/storage is a symlink to the storage disk) and never reaches this
 * middleware at all — see docker/nginx/prod.conf's own auth_request gate on
 * that location, which asks BetaAccessController::authCheck() the exact
 * same session question this middleware asks here.
 */
class BetaAccessGate
{
    /**
     * Laravel's own framework health route (bootstrap/app.php's `health:
     * '/up'`) and the gate's own show/verify routes must always be
     * reachable — otherwise a visitor could never actually get past the
     * gate, and /up would falsely report unhealthy.
     *
     * robots.txt must never be redirected — a crawler expects the literal
     * /robots.txt URL to return crawl rules directly, not a 302 to the
     * password page, and RobotsController already returns a full
     * "Disallow: /" itself while the gate is enabled (its own docblock).
     *
     * internal/beta-auth-check must never be redirected either — it's
     * BetaAccessController::authCheck()'s own 204/401 response that Nginx's
     * auth_request (docker/nginx/prod.conf) depends on for /storage/...; a
     * 302 here would make every storage file 500 out through auth_request
     * instead of correctly gating them.
     */
    private const array EXCLUDED_PATHS = [
        'up',
        'beta-access',
        'beta-access/*',
        'robots.txt',
        'internal/beta-auth-check',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('beta.enabled')) {
            return $next($request);
        }

        if ($request->is(...self::EXCLUDED_PATHS)) {
            return $next($request);
        }

        if ($request->session()->get('beta_access_granted') === true) {
            return $next($request);
        }

        $request->session()->put('beta_access_intended_url', $request->fullUrl());

        return redirect()->route('beta.show');
    }
}
