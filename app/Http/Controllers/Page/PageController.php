<?php

namespace App\Http\Controllers\Page;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Shared\Support\Pages\PageContentRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Public CMS page viewer — the "Stage D" public frontend controller that
 * PreviewPageController's doc comment anticipated, reusing the same
 * PageContentRenderer/`pages.show` view rather than a second, independent
 * rendering implementation. Unlike the preview route, this is unauthenticated
 * and only ever serves published pages.
 */
class PageController extends Controller
{
    public function __invoke(Page $page, PageContentRenderer $renderer): View|RedirectResponse
    {
        abort_unless($page->status === PageStatus::Published, 404);

        // The "home" Page's real, canonical URL is "/" (HomeController) —
        // permanently redirect its slug URL there instead of also rendering
        // it a second time through the generic renderer, which would
        // otherwise be indexable duplicate content at two URLs.
        if ($page->slug === 'home') {
            return redirect()->route('home', [], 301);
        }

        return $renderer->render($page);
    }
}
