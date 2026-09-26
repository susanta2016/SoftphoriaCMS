<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Service;
use App\Shared\Support\Features\Features;
use Illuminate\Http\Response;
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
        $pages = Page::query()->published()->with('seo')->orderBy('slug')->get(['id', 'slug', 'updated_at']);
        $isNoindex = fn (Page $page): bool => str_contains(strtolower($page->seo?->robots ?? ''), 'noindex');

        $home = $pages->firstWhere('slug', 'home');
        $homeIsNoindex = $home && $isNoindex($home);

        $urls = collect($homeIsNoindex ? [] : [['loc' => url('/'), 'lastmod' => $home?->updated_at ?? now()]])
            ->merge(
                $pages->reject(fn (Page $page) => $page->slug === 'home' || $isNoindex($page))
                    ->map(fn (Page $page): array => [
                        'loc' => route('pages.show', $page),
                        'lastmod' => $page->updated_at,
                    ]),
            );

        $urls = $urls->merge($this->serviceUrls())->merge($this->blogUrls());

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
