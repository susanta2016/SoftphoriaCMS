<?php

namespace App\Tools\SeoChecker\Checks;

use App\Tools\SeoChecker\AuditContext;
use App\Tools\SeoChecker\Finding;

/**
 * Performance & mobile, MVP: what one server-side request can honestly
 * measure — server response time, HTML size and compression. Real page
 * load and Core Web Vitals are NOT measured; the report says so and points
 * to PageSpeed Insights instead of inventing numbers.
 */
class PerformanceChecks extends CheckGroup
{
    public const CATEGORY = 'performance';

    public const LABEL = 'Performance & mobile';

    public const SLOW_RESPONSE_MS = 800;

    public const LARGE_HTML_BYTES = 1_000_000;

    public function run(AuditContext $context): array
    {
        return [
            $this->responseTime($context),
            $this->htmlSize($context),
            $this->compression($context),
            $this->notChecked('perf.page_speed', 'Real page speed and Core Web Vitals',
                'Not measured. This checker only times the HTML response; it doesn\'t load images, scripts or styles, or render the page.',
                'Core Web Vitals (loading, responsiveness and visual stability) measure what visitors experience, and are part of Google\'s page experience signals.',
                'Run the page through PageSpeed Insights (pagespeed.web.dev) for lab measurements and, once the site has enough traffic, real-user data.'),
        ];
    }

    private function responseTime(AuditContext $c): Finding
    {
        $ms = $c->page->responseMs;
        $evidence = ["{$ms} ms for the final page response, including connection and TLS setup."];
        if ($c->page->redirects !== []) {
            $evidence[] = "{$c->page->totalMs} ms including redirects.";
        }
        $why = 'A slow server response delays everything else on the page. Under about 0.8 s is a common target for time to first byte.';
        $limitation = 'One measurement from our server\'s location — not a real visitor\'s page load, and not Core Web Vitals.';

        if ($ms > self::SLOW_RESPONSE_MS) {
            return $this->warning('perf.response_time', 'Slow server response', $evidence, $why,
                'Enable page caching (in WordPress, a caching plugin or your host\'s cache), use a CDN, and check slow database queries or plugins. Run the check again to rule out a one-off delay.', $limitation);
        }

        return $this->passed('perf.response_time', 'Server responded quickly', $evidence, $why, 'Nothing to do.', $limitation);
    }

    private function htmlSize(AuditContext $c): Finding
    {
        $bytes = strlen($c->page->body);
        $evidence = number_format($bytes / 1024, 0).' KB of HTML'.($c->page->bytes !== $bytes ? ' ('.number_format($c->page->bytes / 1024, 0).' KB transferred compressed)' : '').'.';
        $why = 'Very large HTML takes longer to download and parse, especially on mobile connections. Page builders and inline data can bloat it.';

        if ($bytes > self::LARGE_HTML_BYTES) {
            return $this->warning('perf.html_size', 'Large HTML document', $evidence, $why,
                'Remove unused page-builder sections, inline SVGs and inline data; load long lists in pages.', 'Only the HTML is measured, not images, scripts or styles.');
        }

        return $this->passed('perf.html_size', 'HTML size is reasonable', $evidence, $why, 'Nothing to do.', 'Only the HTML is measured, not images, scripts or styles.');
    }

    private function compression(AuditContext $c): Finding
    {
        $encoding = strtolower((string) $c->page->header('content-encoding'));
        $why = 'Compression (gzip or Brotli) usually shrinks HTML by 70% or more, so pages arrive faster.';

        if (in_array($encoding, ['gzip', 'x-gzip', 'deflate', 'br', 'zstd'], true)) {
            return $this->passed('perf.compression', 'HTML is compressed', "Content-Encoding: {$encoding}", $why, 'Nothing to do.', 'We asked for gzip or deflate; browsers may receive Brotli instead.');
        }

        if (strlen($c->page->body) < 2048) {
            return $this->passed('perf.compression', 'HTML is too small to need compression', 'No Content-Encoding header; the HTML is under 2 KB.', $why);
        }

        return $this->warning('perf.compression', 'HTML isn\'t compressed', 'No Content-Encoding header, although gzip was requested.', $why,
            'Enable gzip or Brotli in your hosting panel, CDN or server configuration (or with a caching plugin).');
    }
}
