<?php

namespace App\Http\Controllers;

use App\Enums\PageSectionType;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageSection;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * The Softphoria homepage (WEB-102). Content comes from the "home" CMS Page
 * — created/edited through the same Pages module as any other page
 * (ADMIN-006), seeded with Softphoria's real copy by HomePageSeeder — while
 * the fallback values below are deliberately neutral (never another
 * company's identity/claims), so a fresh install with the page not yet
 * created still renders something reasonable instead of an error. The
 * header logo and site_name/tagline read Website Setup's existing general
 * settings — this is site-wide chrome, not page content.
 *
 * The Hero section is rendered here with its own bespoke full-bleed banner
 * markup (resources/views/home.blade.php) since that look is specific to
 * the homepage; every other enabled section on the "home" Page (Who We Are,
 * Services, Expertise, Process, Testimonials, the Contact Form CTA — see
 * HomePageSeeder) is rendered generically through the same x-site.sections
 * component the rest of the public site uses (WEB-102), via $sections.
 */
class HomeController extends Controller
{
    public function __invoke(SettingsRepository $settings): View
    {
        $hero = null;
        $seo = null;
        $sections = new Collection;

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

            $siteName = ($general['site_name'] ?? null) ?: config('app.name');
            $tagline = $general['tagline'] ?? null;
            $logoMediaId = $general['logo_media_id'] ?? null;
            $logo = $logoMediaId ? Media::find($logoMediaId) : null;

            $hero = $this->heroContent($page);
            $sections = $this->nonHeroSections($page);

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
            $siteName = config('app.name');
            $tagline = null;
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
            'sections' => $sections,
            'siteName' => $siteName,
            'tagline' => $tagline,
            'logo' => $logo,
            'seo' => $seo,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function heroContent(?Page $page): array
    {
        // Neutral fallback only — used when the "home" Page or its Hero
        // section doesn't exist yet (fresh install). Never another
        // company's headline/CTAs; those come solely from the real seeded
        // content (HomePageSeeder) or an admin editing the Page.
        $defaults = [
            'heading' => config('app.name'),
            'subheading' => null,
            'media_id' => null,
            'cta_label' => null,
            'cta_url' => null,
            'secondary_cta_label' => null,
            'secondary_cta_url' => null,
            'tertiary_label' => null,
            'tertiary_url' => null,
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
     * Every enabled section on the "home" Page except Hero (rendered
     * separately, see the class docblock) — Who We Are, Services,
     * Expertise, Process, Testimonials, the Contact Form CTA, in
     * sort_order. Rendered generically by x-site.sections, the same
     * component the rest of the public site uses (WEB-102) — no
     * homepage-specific rendering logic duplicated here.
     *
     * @return Collection<int, PageSection>
     */
    private function nonHeroSections(?Page $page): Collection
    {
        return ($page?->sections ?? new Collection)
            ->reject(fn ($section) => $section->section_type === PageSectionType::Hero->value)
            ->values();
    }
}
