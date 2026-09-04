<?php

namespace App\Http\Controllers;

use App\Enums\PageSectionType;
use App\Models\LightPost;
use App\Models\Media;
use App\Models\Page;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use App\Shared\Support\Seo\Sitemapable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * The approved Home_page_layout_V4.1.1.png homepage (WEB-001..005). Content
 * comes from the "home" CMS Page — created/edited through the same Pages
 * module as any other page (ADMIN-006) — while the fallback values below
 * mirror that same approved copy, so a fresh install with the page not yet
 * created still renders the approved layout instead of an error. The header
 * logo and site_name/tagline read Website Setup's existing general settings
 * — this is site-wide chrome, not page content.
 */
class HomeController extends Controller implements Sitemapable
{
    public function __invoke(SettingsRepository $settings): View
    {
        $hero = null;
        $seo = null;
        $gratitude = collect();

        // A freshly deployed/not-yet-migrated environment has neither table
        // yet — same "fail open onto the approved defaults" reasoning as
        // CheckMaintenanceMode, so the homepage never hard-500s here. Every
        // settings/Page/SeoTagBuilder lookup below touches the database, so
        // it all has to stay inside this same try — SeoTagBuilder's own
        // site-wide settings reads (twitter handle, default share image...)
        // would otherwise throw past this guard on an unmigrated database.
        try {
            $page = Page::query()
                ->published()
                ->where('slug', 'home')
                ->with([
                    'sections' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order'),
                    'seo',
                    'featuredImage',
                ])
                ->first();

            // Fetched once and reused for both this method's own site_name/
            // tagline/logo chrome and SeoTagBuilder's site-wide fallbacks
            // below — one query instead of the ~7 individual per-key ones
            // this used to add up to across both call sites.
            $general = $settings->all('general');

            $siteName = ($general['site_name'] ?? null) ?: 'All The Things Light';
            $tagline = ($general['tagline'] ?? null) ?: 'I AM. WE ARE. IT IS.';
            $logoMediaId = $general['logo_media_id'] ?? null;
            $logo = $logoMediaId ? Media::find($logoMediaId) : null;

            $hero = $this->heroContent($page);
            $gratitude = $this->latestGratitudeEntries();

            $seo = SeoTagBuilder::build($page?->seo, [
                'title' => $page?->title ?: $siteName,
                'description' => $page?->summary ?: $hero['subheading'],
                'canonical' => url('/'),
                // The "home" Page's own SeoMetadata row stores an
                // auto-generated canonical_url built from its slug
                // ("/home") — irrelevant here since PageController
                // redirects that URL to "/" precisely to avoid a second
                // indexable copy, so this fallback always wins regardless
                // of what's saved in the admin (affects canonical, og:url,
                // and the WebSite JSON-LD's url alike).
                'force_canonical' => true,
                'image' => $hero['media'] ?? $page?->featuredImage,
                'type' => 'website',
            ], $general);
        } catch (QueryException) {
            $page = null;
            $siteName = 'All The Things Light';
            $tagline = 'I AM. WE ARE. IT IS.';
            $logo = null;
        }

        $hero ??= $this->heroContent(null);

        // Deliberately not a SeoTagBuilder::build() call — that reads
        // site-wide settings too, which would throw the same QueryException
        // this whole method exists to fail open around. A plain static
        // array is the only thing safe to fall back to here.
        $seo ??= [
            'title' => $siteName,
            'description' => $hero['subheading'],
            'keywords' => null,
            'canonical' => url('/'),
            'robots' => 'index, follow',
            'site_name' => $siteName,
            'og_title' => $siteName,
            'og_description' => $hero['subheading'],
            'og_image' => null,
            'og_type' => 'website',
            'twitter_card' => 'summary',
            'twitter_site' => null,
            'twitter_title' => $siteName,
            'twitter_description' => $hero['subheading'],
            'twitter_image' => null,
            'fb_app_id' => null,
            'structured_data' => null,
        ];

        return view('home', [
            'hero' => $hero,
            'community' => $this->communityContent($page),
            'gratitude' => $gratitude,
            'siteName' => $siteName,
            'tagline' => $tagline,
            'logo' => $logo,
            'seo' => $seo,
        ]);
    }

    /**
     * The "home" Page's own SEO tab can mark it noindex, in which case "/"
     * must not appear in the sitemap either — same contradiction-avoidance
     * reasoning as Page::sitemapEntries() (a sitemap listing a URL search
     * engines are simultaneously told not to index is a real error, not
     * just noise).
     */
    public static function sitemapEntries(): Collection
    {
        $home = Page::query()->published()->where('slug', 'home')->with('seo')->first();

        if ($home?->seo?->isNoindex()) {
            return collect();
        }

        return collect([['loc' => url('/'), 'lastmod' => $home?->updated_at ?? now()]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function heroContent(?Page $page): array
    {
        $defaults = [
            'heading' => 'Light is our nature. Love is our purpose.',
            'subheading' => "Music. Writing. Reflection. Thinking. Community.\n\nA space to explore ideas, discover music, and connect with what truly matters.",
            'media_id' => null,
            'cta_label' => 'Explore Music',
            'cta_url' => '#',
            'secondary_cta_label' => 'Read Writing',
            'secondary_cta_url' => '#',
            'tertiary_label' => 'Watch Introduction',
            'tertiary_url' => '#',
            'tertiary_video_media_id' => null,
        ];

        $content = $page?->sections
            ->firstWhere('section_type', PageSectionType::Hero->value)
            ?->content_json ?? [];

        $merged = [...$defaults, ...array_filter($content, fn (mixed $value): bool => $value !== null && $value !== '')];

        $merged['media'] = $merged['media_id'] ? Media::find($merged['media_id']) : null;
        $merged['tertiary_video'] = $merged['tertiary_video_media_id'] ? Media::find($merged['tertiary_video_media_id']) : null;
        $merged['tertiary_embed_url'] = $merged['tertiary_video'] ? null : self::resolveEmbedUrl($merged['tertiary_url'] ?? null);

        return $merged;
    }

    /**
     * YouTube/Vimeo links play in the same on-page popup as an uploaded
     * video (rather than navigating away to a new tab/window) — this turns
     * a normal watch/share URL into its embeddable player URL. Any other
     * URL (or no match) falls back to a plain outbound link in the view.
     */
    private static function resolveEmbedUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (preg_match('#youtu\.be/([\w-]+)#', $url, $matches)
            || preg_match('#youtube\.com/(?:watch\?v=|embed/|shorts/)([\w-]+)#', $url, $matches)) {
            return "https://www.youtube.com/embed/{$matches[1]}?rel=0";
        }

        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $matches)) {
            return "https://player.vimeo.com/video/{$matches[1]}";
        }

        return null;
    }

    /**
     * The "Latest Gratitude" homepage carousel's real content — reuses this
     * existing display slot rather than a parallel mechanism. Client-confirmed
     * (2026-09-04): this section now shows Public Gratitude Journal entries
     * only (source = journal, is_public = true) — registration-time Light
     * Posts are deliberately excluded here and remain their own, separate,
     * untouched feature (still reachable via their own light-posts.show
     * route and still searchable — see LightPost's own docblock). A Private
     * Gratitude Journal entry never reaches this query at all, the same
     * public()-scope guarantee GratitudeJournalVisibilityTest already
     * covers for this method.
     *
     * orderByDesc('id') as a tiebreaker alongside latest() — same reasoning
     * as GratitudeJournalFeedController/InspirationalResourceController's
     * own ordering: several entries can share the same created_at second,
     * and latest() alone leaves those rows in an undefined relative order.
     *
     * @return Collection<int, LightPost>
     */
    private function latestGratitudeEntries(): Collection
    {
        return LightPost::query()->journal()->public()->with('user')->latest()->orderByDesc('id')->limit(8)->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function communityContent(?Page $page): array
    {
        return [
            'enabled' => (bool) $page?->sections->firstWhere('section_type', PageSectionType::FeaturedContent->value),
        ];
    }
}
