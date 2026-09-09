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
 *
 * While the Temporary Beta Access Gate is enabled (config('beta.enabled'),
 * SeoTagBuilder's own docblock), this disallows the entire site instead —
 * every page is already forced noindex, nofollow via SeoTagBuilder, and the
 * gate itself blocks a crawler from reaching any content regardless, but a
 * blanket Disallow is the "appropriate crawler directive" alongside that.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        if (config('beta.enabled')) {
            return response("User-agent: *\nDisallow: /\n")->header('Content-Type', 'text/plain');
        }

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
