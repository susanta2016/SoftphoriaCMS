<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Media;
use App\Models\Service;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Blog\BlogContent;
use App\Shared\Support\Features\Features;
use App\Shared\Support\Seo\SchemaOrg;
use App\Shared\Support\Seo\SeoTagBuilder;
use App\Shared\Support\Services\ServiceSettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The public Services pages: the /services landing page and one detail
 * page per published service (both behind feature:services).
 *
 * SEO: Service + FAQPage + BreadcrumbList JSON-LD on detail pages,
 * CollectionPage + ItemList on the landing page, self-referencing
 * canonicals, and entries in the sitemap. Detail pages cross-link other
 * services and related blog posts (matched by technology tag) to build
 * topical internal links.
 */
class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceSettingsRepository $settings,
        private readonly SettingsRepository $siteSettings,
        private readonly Features $features,
    ) {}

    public function index(): View
    {
        $services = Service::query()->published()->ordered()->with('cover')->get();
        $title = $this->settings->get('title');
        $siteName = $this->siteName();

        return view('services.index', $this->chrome() + [
            'services' => $services,
            'technologies' => $services->flatMap(fn (Service $service): array => $service->technologies ?? [])->unique(fn (string $t): string => mb_strtolower($t))->values(),
            'settings' => $this->settings,
            'portfolioOn' => $this->features->enabled('portfolio'),
            'seo' => SeoTagBuilder::build(null, [
                'title' => $this->settings->get('meta_title') ?: "Services — {$siteName}",
                'description' => $this->settings->get('meta_description') ?: $this->settings->get('intro'),
                'canonical' => route('services.index'),
                'type' => 'website',
                'structured_data' => SchemaOrg::collectionPage(
                    $title,
                    route('services.index'),
                    [['Services', route('services.index')]],
                    $services->map(fn (Service $service): array => [$service->title, $service->url()])->all(),
                ),
            ]),
        ]);
    }

    public function show(Service $service): View
    {
        $isPreview = ! $service->is_published;
        abort_if($isPreview && ! $this->canPreview(), 404);

        $service->load(['cover', 'seo']);
        $content = BlogContent::prepare($service->body);
        $siteName = $this->siteName();
        $coverUrl = $service->cover ? Storage::disk($service->cover->disk)->url($service->cover->path) : null;
        $canonical = $service->seo?->canonical_url ?: $service->url();

        return view('services.show', $this->chrome() + [
            'service' => $service,
            'isPreview' => $isPreview,
            'bodyHtml' => $content['html'],
            'coverUrl' => $coverUrl,
            'others' => Service::query()->published()->ordered()->whereKeyNot($service->getKey())->limit(3)->get(),
            'relatedPosts' => $this->relatedPosts($service),
            'settings' => $this->settings,
            'seo' => SeoTagBuilder::build($service->seo, [
                'title' => "{$service->title} — {$siteName}",
                'description' => $service->summary,
                'canonical' => $service->url(),
                'image' => $service->cover,
                'type' => 'website',
                'robots' => $isPreview ? SeoTagBuilder::ROBOTS_NOINDEX : null,
                'structured_data' => [
                    '@context' => 'https://schema.org',
                    '@graph' => array_values(array_filter([
                        array_filter([
                            '@type' => 'Service',
                            'name' => $service->title,
                            'serviceType' => $service->title,
                            'description' => $service->summary,
                            'url' => $canonical,
                            'image' => $coverUrl,
                            'provider' => array_filter([
                                '@type' => 'Organization',
                                'name' => $siteName,
                                'url' => route('home'),
                                'logo' => $this->logoUrl(),
                            ]),
                            'hasOfferCatalog' => filled($service->highlights) ? [
                                '@type' => 'OfferCatalog',
                                'name' => "{$service->title} — what's included",
                                'itemListElement' => collect($service->highlights)->map(fn (array $item): array => [
                                    '@type' => 'Offer',
                                    'itemOffered' => array_filter(['@type' => 'Service', 'name' => $item['title'] ?? null, 'description' => $item['description'] ?? null]),
                                ])->values()->all(),
                            ] : null,
                        ]),
                        SchemaOrg::faqPage($service->faqs),
                        SchemaOrg::breadcrumbs([['Services', route('services.index')], [$service->title, $service->url()]]),
                    ])),
                ],
            ]),
        ]);
    }

    /**
     * Live blog posts tagged with one of the service's technologies.
     *
     * @return Collection<int, BlogPost>
     */
    private function relatedPosts(Service $service): Collection
    {
        $technologies = collect($service->technologies ?? [])->map(fn (string $t): string => mb_strtolower($t))->all();

        if ($technologies === [] || ! $this->settings->get('show_related_posts') || $this->features->disabled('blog.posts')) {
            return collect();
        }

        return BlogPost::query()
            ->live()
            ->with(['cover', 'category', 'author'])
            ->whereHas('tags', fn (Builder $query) => $query->whereIn(DB::raw('lower(name)'), $technologies))
            ->latestFirst()
            ->limit(3)
            ->get();
    }

    /**
     * @return array{siteName: string, tagline: ?string, logo: ?Media}
     */
    private function chrome(): array
    {
        $logoId = $this->siteSettings->get('general', 'logo_media_id');

        return [
            'siteName' => $this->siteName(),
            'tagline' => $this->siteSettings->get('general', 'tagline'),
            'logo' => $logoId ? Media::find($logoId) : null,
        ];
    }

    private function siteName(): string
    {
        return $this->siteSettings->get('general', 'site_name') ?: config('app.name');
    }

    private function logoUrl(): ?string
    {
        $logo = $this->chrome()['logo'];

        return $logo ? Storage::disk($logo->disk)->url($logo->path) : null;
    }

    private function canPreview(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->canAccessPanel(filament()->getPanel('admin'));
    }
}
