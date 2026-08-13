<?php

namespace App\Http\Middleware;

use App\Models\Page;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Pages\PageContentRenderer;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Website Setup's Maintenance Mode (docs/ARCHITECTURE.md §16.3). Applied
 * globally to the `web` middleware group (bootstrap/app.php) rather than to
 * specific route(s) — Stage D's public frontend does not exist yet, so this
 * guards by path exclusion instead of an allowlist of public routes, and
 * needs no changes once those routes are added.
 *
 * Renders the selected page directly through the same PageContentRenderer
 * the admin Preview action already uses (ADMIN-006 review fix) — no second
 * "maintenance page" rendering path is introduced. Because the page is
 * rendered directly rather than re-dispatched through routing, the selected
 * page can never trigger its own maintenance check again.
 */
class CheckMaintenanceMode
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly PageContentRenderer $renderer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // The entire Filament panel (including its login page) and
        // Livewire's own AJAX/asset endpoints — which every admin-panel
        // interaction depends on and do not live under /admin/* — must
        // always stay reachable so an admin can turn maintenance mode back
        // off. /up is the framework health-check route.
        //
        // Livewire 4 does not use a fixed "/livewire/*" path: it derives a
        // per-installation prefix from APP_KEY (EndpointResolver::prefix(),
        // e.g. "/livewire-f1db4272") specifically to make the endpoint
        // harder to guess. A hardcoded "livewire/*" exclusion silently
        // misses this — which was caught in browser verification when
        // enabling Maintenance Mode intercepted the admin's own Save
        // request. Deriving the prefix here keeps the exclusion correct
        // regardless of what it resolves to.
        $livewirePrefix = ltrim(EndpointResolver::prefix(), '/');

        if ($request->is('admin', 'admin/*', "{$livewirePrefix}/*", 'up')) {
            return $next($request);
        }

        // Every request touches this middleware, including on a freshly
        // deployed/not-yet-migrated environment where the `settings` table
        // doesn't exist yet — that must never turn into a hard 500 on every
        // single page. Fail open (behave as if maintenance mode is off)
        // rather than crash.
        try {
            $maintenanceModeEnabled = (bool) $this->settings->get('general', 'maintenance_mode', false);
        } catch (QueryException) {
            return $next($request);
        }

        if (! $maintenanceModeEnabled) {
            return $next($request);
        }

        $pageId = $this->settings->get('general', 'maintenance_page_id');
        $page = $pageId ? Page::query()->published()->find($pageId) : null;

        // A missing/unpublished/deleted maintenance page must never turn
        // into a hard error — fall through to normal routing instead.
        if (! $page) {
            return $next($request);
        }

        return response($this->renderer->render($page), 503);
    }
}
