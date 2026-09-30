<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\PortfolioItem;
use App\Models\Service;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Features\Features;
use App\Shared\Support\Seo\SchemaOrg;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * /portfolio — every published portfolio project (Admin → Portfolio), with
 * category chips that filter via ?category= (swapped in place through the
 * shared [data-async-region] script in resources/js/app.js, crawlable as
 * plain links without JS). The homepage Featured Portfolio section's
 * "View All Projects" link lands here. Behind feature:portfolio.
 *
 * /portfolio/{slug} — one project, rendered by the same template for every
 * item. Only published projects are public; an unpublished one 404s for
 * everyone except a signed-in admin, who gets a noindex preview (the same
 * rule as ServiceController). Detail sections only show CMS content.
 */
class PortfolioController extends Controller
{
    public function __invoke(Request $request, SettingsRepository $settings): Response
    {
        $items = PortfolioItem::query()->published()->ordered()->with(['cover', 'services' => fn ($query) => $query->published()])->get();
        $categories = $items->pluck('category')->filter()->unique()->values();

        $active = (string) $request->query('category', '');
        $active = $categories->first(fn (string $category): bool => $category === $active) ?? '';
        $shown = $active === '' ? $items : $items->where('category', $active)->values();

        $siteName = $this->siteName($settings);

        $data = $this->chrome($settings) + [
            'items' => $shown,
            'categories' => $categories,
            'active' => $active,
            'seo' => SeoTagBuilder::build(null, [
                // Website Setup → SEO holds the list's own title/description;
                // a category-filtered view keeps its generated title.
                'title' => $active !== ''
                    ? "{$active} projects — {$siteName}"
                    : ($settings->get('portfolio', 'meta_title') ?: "Portfolio — {$siteName}"),
                'description' => $settings->get('portfolio', 'meta_description')
                    ?: "Selected projects by {$siteName} — websites, platforms and technology solutions delivered across different industries.",
                // Filtered views are the same projects again: canonicalise to the full list.
                'canonical' => route('portfolio.index'),
                'type' => 'website',
                'structured_data' => SchemaOrg::collectionPage(
                    'Portfolio',
                    route('portfolio.index'),
                    [['Portfolio', route('portfolio.index')]],
                    $shown->map(fn (PortfolioItem $item): array => [$item->title, $item->url()])->all(),
                ),
            ]),
        ];

        return response()
            ->view($request->ajax() ? 'portfolio.partials.page' : 'portfolio.index', $data)
            ->header('Vary', 'X-Requested-With');
    }

    public function show(PortfolioItem $item, SettingsRepository $settings, Features $features): View
    {
        $isPreview = ! $item->is_published;
        abort_if($isPreview && ! $this->canPreview(), 404);

        $item->load(['cover', 'seo', 'services' => fn ($query) => $query->published()]);

        $siteName = $this->siteName($settings);
        $coverUrl = $item->cover ? Storage::disk($item->cover->disk)->url($item->cover->path) : null;

        // Only approved, admin-entered detail text is shown — an empty
        // section never renders a heading.
        $sections = collect(['Challenge' => $item->challenge, 'Solution' => $item->solution, 'Outcome' => $item->outcome])
            ->map(fn (?string $html): string => Str::sanitizeHtml((string) $html))
            ->filter(fn (string $html): bool => trim(strip_tags($html, '<img>')) !== '');

        return view('portfolio.show', $this->chrome($settings) + [
            'item' => $item,
            'isPreview' => $isPreview,
            'coverUrl' => $coverUrl,
            'sections' => $sections,
            'gallery' => $item->galleryMedia(),
            'linkServices' => $features->enabled('services'),
            'seo' => SeoTagBuilder::build($item->seo, [
                'title' => "{$item->title} — {$siteName}",
                'description' => $item->summary,
                'canonical' => $item->url(),
                'image' => $item->cover,
                'type' => 'website',
                'robots' => $isPreview ? SeoTagBuilder::ROBOTS_NOINDEX : null,
                'structured_data' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        array_filter([
                            '@type' => 'CreativeWork',
                            'name' => $item->title,
                            'description' => $item->summary,
                            'url' => $item->seo?->canonical_url ?: $item->url(),
                            'image' => $coverUrl,
                            'genre' => $item->category,
                            'keywords' => filled($item->technologies) ? implode(', ', $item->technologies) : null,
                            'about' => $item->services->isNotEmpty()
                                ? $item->services->map(fn (Service $service): array => ['@type' => 'Service', 'name' => $service->title])->values()->all()
                                : null,
                            'creator' => ['@type' => 'Organization', 'name' => $siteName, 'url' => route('home')],
                        ]),
                        SchemaOrg::breadcrumbs([['Portfolio', route('portfolio.index')], [$item->title, $item->url()]]),
                    ],
                ],
            ]),
        ]);
    }

    /**
     * @return array{siteName: string, tagline: ?string, logo: ?Media}
     */
    private function chrome(SettingsRepository $settings): array
    {
        $logoId = $settings->get('general', 'logo_media_id');

        return [
            'siteName' => $this->siteName($settings),
            'tagline' => $settings->get('general', 'tagline'),
            'logo' => $logoId ? Media::find($logoId) : null,
        ];
    }

    private function siteName(SettingsRepository $settings): string
    {
        return $settings->get('general', 'site_name') ?: config('app.name');
    }

    private function canPreview(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->canAccessPanel(filament()->getPanel('admin'));
    }
}
