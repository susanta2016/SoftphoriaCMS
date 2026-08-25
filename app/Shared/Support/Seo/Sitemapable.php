<?php

namespace App\Shared\Support\Seo;

use Illuminate\Support\Collection;

/**
 * Implemented by any class — an Eloquent model (Page, and every future
 * public content type: Album, Single, PodcastEpisode, Poetry/Prose entry,
 * ...) or a controller for a single static route (HomeController) — that
 * contributes URLs to /sitemap.xml. Registered once in
 * config('seo.sitemap_sources'); SitemapController itself never knows
 * which content types exist, only that whatever is registered implements
 * this.
 *
 * docs/development instructions for SEO.docx §1/§6/§9: the sitemap must
 * never be hard-coded to today's content types ("new eligible public
 * pages/content can automatically become included without requiring
 * manual sitemap modification"), and the actual eligibility filtering
 * (published + indexable + canonical) belongs centralized next to each
 * type's own data, not duplicated/scattered inside SitemapController as
 * each module gains a public route.
 *
 * --- When to implement this, and when NOT to (Cory's requirement) ---
 * Implement it when a content type gets a genuinely PUBLIC route: mirror
 * Page::sitemapEntries() exactly — a published/visibility scope, then
 * `->reject(fn ($record) => ($record->seo?->isNoindex() ?? false) ||
 * ($record->seo?->canonicalPointsElsewhere($url) ?? false))`, then map to
 * `['loc' => ..., 'lastmod' => ...]` — the same three checks, never fewer.
 *
 * Do NOT implement this for a private/member-only type merely because it
 * has a database record and a public_id/route-key — a Gratitude Journal
 * entry, a Light Post, an Account/Profile page, or premium/entitlement-
 * gated content must never appear here even if a public URL for it someday
 * exists, because "private" is an access-control property (see
 * AdminPanelProvider's auth middleware / a future member-auth middleware
 * for the pattern to follow), not something this interface's eligibility
 * checks (published/noindex/canonical) express. If such a page is ever
 * rendered at all, it must instead pass
 * `SeoTagBuilder::ROBOTS_NOINDEX` as its `robots` fallback — see
 * SeoTagBuilder's docblock — and simply never be registered in
 * config('seo.sitemap_sources').
 */
interface Sitemapable
{
    /**
     * Only public, published, indexable (not noindex), self-canonical
     * entries — see SeoMetadata::isNoindex()/canonicalPointsElsewhere().
     *
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    public static function sitemapEntries(): Collection;
}
