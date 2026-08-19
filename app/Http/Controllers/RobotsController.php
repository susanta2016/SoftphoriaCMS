<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Dynamic so the Sitemap: line always matches the real configured app URL
 * (config('app.url')) instead of a hardcoded domain baked into a static
 * public/robots.txt — same "never hardcode the domain" rule SeoFields'
 * canonical URL generation already follows.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow:',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
