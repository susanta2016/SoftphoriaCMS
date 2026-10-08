<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\PortfolioItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Tool;
use App\Shared\Support\Features\Features;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A minimal XML sitemap covering the home page plus every published CMS
 * Page — currently the entire public surface (Music/Podcast/Poetry-Prose/
 * Resources have no public routes yet, see docs/ARCHITECTURE.md and the
 * Pages module's own scope notes). Extend this as each module gains a
 * public route rather than standing up a second sitemap.
 *
 * A page whose own SEO tab sets Robots to "noindex" is excluded outright —
 * submitting an admin-marked-noindex URL in the sitemap would tell
 * crawlers to both fetch it (sitemap) and not index it (robots meta), a
 * contradiction search engines flag as a real error, not just noise.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = Page::query()->published()->with('seo')->orderBy('slug')->get(['id', 'slug', 'is_tool_guide', 'updated_at'])
            // Tool guides are only reachable while Tools is switched on.
            ->reject(fn (Page $page): bool => $page->is_tool_guide && app(Features::class)->disabled('tools'));
        $isNoindex = fn (Page $page): bool => str_contains(strtolower($page->seo?->robots ?? ''), 'noindex');

        $home = $pages->firstWhere('slug', 'home');
        $homeIsNoindex = $home && $isNoindex($home);

        $urls = collect($homeIsNoindex ? [] : [['loc' => url('/'), 'lastmod' => $home?->updated_at ?? now()]])
            ->merge(
                $pages->reject(fn (Page $page) => $page->slug === 'home' || $isNoindex($page))
                    ->map(fn (Page $page): array => [
                        'loc' => $page->url(),
                        'lastmod' => $page->updated_at,
                    ]),
            );

        // The Contact page is always public and indexable (not feature-gated);
        // its details and SEO come from the "contact" settings group.
        $contactUpdatedAt = Setting::query()->forGroup('contact')->max('updated_at');
        $urls = $urls->push(['loc' => route('contact.index'), 'lastmod' => $contactUpdatedAt ? Carbon::parse($contactUpdatedAt) : null]);

        $urls = $urls->merge($this->serviceUrls())->merge($this->portfolioUrls())->merge($this->toolUrls())->merge($this->blogUrls());

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * The /services landing page and every published service, while
     * Services Pages is switched on — skipping noindex or canonicalised
     * services.
     *
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    private function serviceUrls(): Collection
    {
        if (app(Features::class)->disabled('services')) {
            return collect();
        }

        $services = Service::query()->published()->with('seo')->ordered()->get();

        return collect([['loc' => route('services.index'), 'lastmod' => $services->max('updated_at') ?? now()]])
            ->merge($services
                ->reject(fn (Service $service): bool => str_contains(strtolower($service->seo?->robots ?? ''), 'noindex')
                    || (filled($service->seo?->canonical_url) && $service->seo->canonical_url !== $service->url()))
                ->map(fn (Service $service): array => ['loc' => $service->url(), 'lastmod' => $service->updated_at]))
            ->values();
    }

    /**
     * /portfolio and every published project page, while Portfolio is
     * switched on and has published projects — skipping noindex or
     * canonicalised projects. Unpublished projects are never listed.
     *
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    private function portfolioUrls(): Collection
    {
        if (app(Features::class)->disabled('portfolio')) {
            return collect();
        }

        $items = PortfolioItem::query()->published()->with('seo')->ordered()->get();

        if ($items->isEmpty()) {
            return collect();
        }

        return collect([['loc' => route('portfolio.index'), 'lastmod' => Carbon::parse($items->max('updated_at'))]])
            ->merge($items
                ->reject(fn (PortfolioItem $item): bool => str_contains(strtolower($item->seo?->robots ?? ''), 'noindex')
                    || (filled($item->seo?->canonical_url) && $item->seo->canonical_url !== $item->url()))
                ->map(fn (PortfolioItem $item): array => ['loc' => $item->url(), 'lastmod' => $item->updated_at]))
            ->values();
    }

    /**
     * /tools and every live tool (published, functionality deployed) while
     * Tools Pages is switched on — skipping noindex or canonicalised tools.
     * Drafts, unpublished tools and previews are never listed.
     *
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    private function toolUrls(): Collection
    {
        if (app(Features::class)->disabled('tools')) {
            return collect();
        }

        $tools = Tool::query()->live()->with('seo')->ordered()->get();

        if ($tools->isEmpty()) {
            return collect();
        }

        return collect([['loc' => route('tools.index'), 'lastmod' => $tools->max('updated_at')]])
            ->merge($tools
                ->reject(fn (Tool $tool): bool => str_contains(strtolower($tool->seo?->robots ?? ''), 'noindex')
                    || (filled($tool->seo?->canonical_url) && $tool->seo->canonical_url !== $tool->url()))
                ->map(fn (Tool $tool): array => ['loc' => $tool->url(), 'lastmod' => $tool->updated_at]))
            ->values();
    }

    /**
     * The blog landing page, every live post, and every category archive
     * that has live posts — only while the matching feature is switched on,
     * and never a record marked noindex or canonicalised to another URL.
     * Tag archives are deliberately left out (they render noindex).
     *
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    private function blogUrls(): Collection
    {
        $features = app(Features::class);

        if ($features->disabled('blog.posts')) {
            return collect();
        }

        $excluded = fn (BlogPost|BlogCategory $record): bool => str_contains(strtolower($record->seo?->robots ?? ''), 'noindex')
            || (filled($record->seo?->canonical_url) && $record->seo->canonical_url !== $record->url());

        $posts = BlogPost::query()->live()->with('seo')->latestFirst()->get(['id', 'slug', 'updated_at', 'published_at', 'blog_category_id']);

        $urls = collect([['loc' => route('blog.index'), 'lastmod' => $posts->max('updated_at') ?? now()]])
            ->merge($posts->reject($excluded)->map(fn (BlogPost $post): array => ['loc' => $post->url(), 'lastmod' => $post->updated_at]));

        if ($features->enabled('blog.categories')) {
            $urls = $urls->merge(BlogCategory::query()
                ->with('seo')
                ->whereIn('id', $posts->pluck('blog_category_id')->filter()->unique())
                ->get()
                ->reject($excluded)
                ->map(fn (BlogCategory $category): array => [
                    'loc' => $category->url(),
                    'lastmod' => $posts->where('blog_category_id', $category->id)->max('updated_at'),
                ]));
        }

        return $urls->values();
    }
}
