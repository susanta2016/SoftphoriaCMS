<?php

namespace App\Shared\Support\Pages;

use App\Enums\PageStatus;
use App\Filament\Support\Seo\SeoFields;
use App\Models\Page;
use App\Shared\Support\Seo\SeoTagBuilder;
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
            'author',
        ]);

        $seo = SeoTagBuilder::build($page->seo, [
            'title' => $page->title,
            'description' => $page->summary,
            'canonical' => SeoFields::autoCanonicalUrl($page->slug),
            'image' => $page->featuredImage,
            'type' => 'article',
            'published_at' => $page->publish_at ?? $page->created_at,
            'modified_at' => $page->updated_at,
            'author_name' => $page->author?->name,
        ]);

        // An unpublished page (admin preview) is never indexable, whatever
        // its own saved SEO robots value says — same rule the preview
        // banner/title prefix already enforce.
        if ($page->status !== PageStatus::Published) {
            $seo['robots'] = 'noindex, nofollow';
        }

        return view('pages.show', ['page' => $page, 'seo' => $seo]);
    }
}
