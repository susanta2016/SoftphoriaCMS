<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Dynamic so the Sitemap: line always matches the real configured app URL
 * (config('app.url')) instead of a hardcoded domain baked into a static
 * public/robots.txt — same "never hardcode the domain" rule SeoFields'
 * canonical URL generation already follows.
 *
 * `/admin` is disallowed here purely as crawl-budget hygiene for a
 * system/non-public URL pattern (docs/development instructions for SEO.docx
 * §4) — it is never the actual access control for the panel, which is
 * AdminPanelProvider's own auth middleware (see §3: "robots.txt must never
 * be treated as a security mechanism").
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
