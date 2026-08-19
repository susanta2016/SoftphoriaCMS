<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Response;

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

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
