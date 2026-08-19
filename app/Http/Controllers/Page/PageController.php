<?php

namespace App\Http\Controllers\Page;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Shared\Support\Pages\PageContentRenderer;
use Illuminate\Contracts\View\View;

/**
 * Public CMS page viewer — the "Stage D" public frontend controller that
 * PreviewPageController's doc comment anticipated, reusing the same
 * PageContentRenderer/`pages.show` view rather than a second, independent
 * rendering implementation. Unlike the preview route, this is unauthenticated
 * and only ever serves published pages.
 */
class PageController extends Controller
{
    public function __invoke(Page $page, PageContentRenderer $renderer): View
    {
        abort_unless($page->status === PageStatus::Published, 404);

        return $renderer->render($page);
    }
}
