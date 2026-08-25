<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * A centralized, content-type-agnostic XML sitemap (docs/development
 * instructions for SEO.docx §1/§6/§9). This controller knows nothing about
 * Page, Music, Podcast, or any other module — it only loops over
 * config('seo.sitemap_sources') and merges each registered source's own
 * App\Shared\Support\Seo\Sitemapable::sitemapEntries(). Adding a new public
 * content type means implementing that interface on its model and adding
 * the class to config/seo.php — nothing in this controller ever changes,
 * and the actual published/indexable/canonical eligibility logic lives
 * once, next to each type's own data, not duplicated here per module.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect(config('seo.sitemap_sources'))
            ->flatMap(fn (string $source) => $source::sitemapEntries());

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
