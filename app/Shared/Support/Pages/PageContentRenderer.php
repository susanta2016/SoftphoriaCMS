<?php

namespace App\Shared\Support\Pages;

use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Filament\Support\Seo\SeoFields;
use App\Models\Media;
use App\Models\Page;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;

/**
 * The single place that turns a Page's structured sections into markup —
 * used by both the public CMS page route (PageController) and the
 * admin-only preview route (PreviewPageController), so a page always
 * previews exactly what visitors will see: the real site header/footer
 * chrome (same as HomeController/home.blade.php), not a bare document.
 *
 * Exception: whichever page is currently selected as Website Setup's
 * Maintenance Page renders standalone, with no header/footer, everywhere
 * it's shown (its own public URL, an admin preview, and the actual
 * maintenance-mode 503 display via CheckMaintenanceMode) — its whole point
 * is to stand in when the rest of the site may be down, so it must never
 * link out to normal nav/footer chrome that could itself be broken.
 *
 * The About template is the one exception to the shared `pages.show`
 * renderer: it gets its own `pages.about` view carrying its bespoke
 * presentation (About page UI/UX refinement task), while every other
 * template (Standard/Faq/Legal/Contact/Archive/Custom) keeps rendering
 * through the generic `pages.show` block-by-block renderer unchanged.
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

        $settings = app(SettingsRepository::class);
        $general = $settings->all('general');

        $isMaintenancePage = ((int) ($general['maintenance_page_id'] ?? 0)) === $page->id;

        $siteName = ($general['site_name'] ?? null) ?: 'All The Things Light';
        $tagline = ($general['tagline'] ?? null) ?: 'I AM. WE ARE. IT IS.';
        $logoMediaId = $general['logo_media_id'] ?? null;
        $logo = $logoMediaId ? Media::find($logoMediaId) : null;

        $seo = SeoTagBuilder::build($page->seo, [
            'title' => $page->title,
            'description' => $page->summary,
            'canonical' => SeoFields::autoCanonicalUrl($page->slug),
            'image' => $page->featuredImage,
            'type' => 'article',
            'published_at' => $page->publish_at ?? $page->created_at,
            'modified_at' => $page->updated_at,
            'author_name' => $page->author?->name,
        ], $general);

        // An unpublished page (admin preview) is never indexable, whatever
        // its own saved SEO robots value says — same rule the preview
        // banner/title prefix below enforce.
        if ($page->status !== PageStatus::Published) {
            $seo['robots'] = 'noindex, nofollow';
            $seo['title'] = 'Preview: '.$seo['title'];
        }

        $view = $page->template === PageTemplate::About ? 'pages.about' : 'pages.show';

        return view($view, [
            'page' => $page,
            'seo' => $seo,
            'siteName' => $siteName,
            'tagline' => $tagline,
            'logo' => $logo,
            'showChrome' => ! $isMaintenancePage,
        ]);
    }
}
