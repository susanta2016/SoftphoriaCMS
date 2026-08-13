<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Shared\Support\Pages\PageContentRenderer;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-only Page preview (ADMIN-006 review fix) — opens in a new browser
 * tab from the edit form's Preview action, never embedded in the Filament
 * form itself. Same auth gate pattern as StreamMediaController (ADMIN-005):
 * enforced inside the controller, not route middleware, since this isn't a
 * panel route. Not a public route — Stage D's public frontend, once it
 * exists, is expected to reuse PageContentRenderer rather than this
 * controller's admin-only gate.
 */
class PreviewPageController extends Controller
{
    public function __invoke(Page $page, PageContentRenderer $renderer): View
    {
        abort_unless(
            Auth::check() && Auth::user()->canAccessPanel(Filament::getPanel('admin')),
            403,
        );

        return $renderer->render($page);
    }
}
