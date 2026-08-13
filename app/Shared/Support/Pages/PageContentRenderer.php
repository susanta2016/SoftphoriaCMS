<?php

namespace App\Shared\Support\Pages;

use App\Models\Page;
use Illuminate\Contracts\View\View;

/**
 * The single place that turns a Page's structured sections into markup.
 * Today it is called only by the admin-only preview route (ADMIN-006 review
 * fix — Preview must open in a new tab and render real structured content,
 * not an admin-only summary panel embedded in the edit form). It is
 * deliberately kept outside the Filament/admin layer so Stage D's public
 * frontend controller can call this exact same renderer later instead of a
 * second, independent page-rendering implementation.
 */
class PageContentRenderer
{
    public function render(Page $page): View
    {
        $page->loadMissing([
            'sections' => fn ($query) => $query->orderBy('sort_order'),
            'featuredImage',
            'seo',
        ]);

        return view('pages.show', ['page' => $page]);
    }
}
