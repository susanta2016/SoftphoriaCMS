<?php

namespace App\Actions\Page;

use App\Enums\PageStatus;
use App\Models\PageRedirect;
use Illuminate\Http\RedirectResponse;

/**
 * WEB-101 item G — page_redirects rows are already written automatically
 * (UpdatePageAction, whenever a published page's slug changes) but were
 * never consulted by routing, so visiting an old slug just 404'd. This is
 * the public {page:slug} route's `missing()` fallback (routes/web.php):
 * it only runs once implicit route-model binding has already failed to
 * find a *live* Page with the requested slug, so it can never shadow a
 * normal page view. It also can't loop: it always redirects straight to
 * the target page's *current* slug/URL, which — being a live slug — the
 * primary route resolves directly on the next request without ever
 * reaching this fallback again.
 */
class ResolvePageRedirectAction
{
    public function handle(string $oldSlug): ?RedirectResponse
    {
        $redirect = PageRedirect::query()
            ->where('old_path', $oldSlug)
            ->where('is_active', true)
            ->first();

        if (! $redirect) {
            return null;
        }

        $page = $redirect->page;

        if (! $page || $page->status !== PageStatus::Published) {
            return null;
        }

        $status = (int) $redirect->redirect_type;
        $status = in_array($status, [301, 302, 307, 308], true) ? $status : 301;

        $url = $page->slug === 'home' ? route('home') : route('pages.show', $page->slug);

        return redirect($url, $status);
    }
}
