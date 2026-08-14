<?php

namespace App\Http\Controllers;

use App\Enums\PageSectionType;
use App\Models\Media;
use App\Models\Page;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;

/**
 * The approved Home_page_layout_V4.1.1.png homepage (WEB-001..005). Content
 * comes from the "home" CMS Page — created/edited through the same Pages
 * module as any other page (ADMIN-006) — while the fallback values below
 * mirror that same approved copy, so a fresh install with the page not yet
 * created still renders the approved layout instead of an error. The header
 * logo and site_name/tagline read Website Setup's existing general settings
 * — this is site-wide chrome, not page content.
 */
class HomeController extends Controller
{
    public function __invoke(SettingsRepository $settings): View
    {
        // A freshly deployed/not-yet-migrated environment has neither table
        // yet — same "fail open onto the approved defaults" reasoning as
        // CheckMaintenanceMode, so the homepage never hard-500s here.
        try {
            $page = Page::query()
                ->published()
                ->where('slug', 'home')
                ->with([
                    'sections' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order'),
                    'seo',
                ])
                ->first();

            $siteName = $settings->get('general', 'site_name') ?: 'All The Things Light';
            $tagline = $settings->get('general', 'tagline') ?: 'I AM. WE ARE. IT IS.';
            $logoMediaId = $settings->get('general', 'logo_media_id');
            $logo = $logoMediaId ? Media::find($logoMediaId) : null;
        } catch (QueryException) {
            $page = null;
            $siteName = 'All The Things Light';
            $tagline = 'I AM. WE ARE. IT IS.';
            $logo = null;
        }

        return view('home', [
            'hero' => $this->heroContent($page),
            'community' => $this->communityContent($page),
            'siteName' => $siteName,
            'tagline' => $tagline,
            'logo' => $logo,
            'seoTitle' => $page?->seo?->meta_title ?: $page?->title ?: 'Home',
            'seoDescription' => $page?->seo?->meta_description ?: $page?->summary,
        ]);
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
        ];

        $content = $page?->sections
            ->firstWhere('section_type', PageSectionType::Hero->value)
            ?->content_json ?? [];

        $merged = [...$defaults, ...array_filter($content, fn (mixed $value): bool => $value !== null && $value !== '')];

        $merged['media'] = $merged['media_id'] ? Media::find($merged['media_id']) : null;

        return $merged;
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
