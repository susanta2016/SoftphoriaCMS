<?php

namespace App\Tools\SeoChecker\Checks;

use App\Tools\SeoChecker\AuditContext;
use App\Tools\SeoChecker\AuditException;
use App\Tools\SeoChecker\Finding;
use App\Tools\SeoChecker\StagingHosts;

/**
 * HTTP & URL: status, redirects, HTTPS, the http:// → https:// redirect and
 * staging-looking hostnames.
 */
class HttpChecks extends CheckGroup
{
    public const CATEGORY = 'http';

    public const LABEL = 'HTTP & URL';

    public function run(AuditContext $context): array
    {
        return array_values(array_filter([
            $this->status($context),
            $this->redirects($context),
            $this->https($context),
            $this->httpsRedirect($context),
            $this->stagingHost($context),
        ]));
    }

    private function status(AuditContext $c): Finding
    {
        $status = $c->page->status;
        $evidence = "HTTP {$status} from {$c->finalUrl()}";
        $why = 'Search engines only index pages that load normally (HTTP 200). Error and access-denied responses are dropped from search results.';

        if ($c->page->successful()) {
            return $this->passed('http.status', "The page loads (HTTP {$status})", $evidence, $why);
        }

        if (in_array($status, [401, 403], true) && $c->isStaging()) {
            return $this->passed('http.status', "The page is protected (HTTP {$status})", $evidence,
                'Protecting a staging copy with a password or IP restriction is the most reliable way to keep it out of search results.',
                'Nothing to do now. Remove the protection on the production site at launch, then run this checker on the live address.',
                'Because the page is protected, its content could not be checked.');
        }

        [$title, $fix] = match (true) {
            in_array($status, [401, 403], true) => ['Access is denied (HTTP '.$status.')', 'Remove password protection, IP restrictions or firewall rules that block visitors and crawlers from the public site. If a security plugin or CDN blocks automated requests, allow verified search engine crawlers.'],
            in_array($status, [404, 410], true) => ['The page is not found (HTTP '.$status.')', 'Check the address. If the page should exist, publish it or restore its permalink; if it moved, redirect the old address with a 301 to the new one.'],
            $status === 429 => ['The server is rate-limiting requests (HTTP 429)', 'Check your firewall, CDN or hosting rate limits; crawlers that receive 429 slow down or stop crawling.'],
            $status >= 500 => ['The server returns an error (HTTP '.$status.')', 'Check the server or application error logs (for WordPress, enable WP_DEBUG_LOG temporarily) and fix the error before launch.'],
            default => ['The page doesn\'t return a normal response (HTTP '.$status.')', 'Make sure the address returns HTTP 200 with the page content.'],
        };

        return $this->critical('http.status', $title, $evidence, $why, $fix);
    }

    private function redirects(AuditContext $c): Finding
    {
        $hops = $c->page->redirects;
        $why = 'Each redirect adds a delay for visitors and crawlers; long chains waste crawl effort and can lose signals. Permanent moves should use 301 or 308.';

        if ($hops === []) {
            return $this->passed('http.redirects', 'No redirects', "{$c->submittedUrl} loads directly.", $why);
        }

        $evidence = array_map(fn (array $hop): string => "{$hop['url']} → HTTP {$hop['status']}", $hops);
        $evidence[] = "Final: {$c->finalUrl()} (HTTP {$c->page->status})";
        $temporary = array_filter($hops, fn (array $hop): bool => in_array($hop['status'], [302, 303, 307], true));

        if (count($hops) > 1 || $temporary !== []) {
            return $this->warning('http.redirects',
                count($hops) > 1 ? 'Redirect chain of '.count($hops).' steps' : 'Temporary redirect used',
                $evidence, $why,
                'Redirect straight to the final address in a single step, and use a 301 (or 308) for permanent moves such as http → https and www/non-www. 302 and 307 are for temporary moves only.');
        }

        return $this->passed('http.redirects', 'One permanent redirect', $evidence, $why);
    }

    private function https(AuditContext $c): Finding
    {
        $why = 'Browsers mark plain-HTTP pages as "Not secure", and HTTPS is a lightweight Google ranking signal.';

        if (str_starts_with($c->finalUrl(), 'https://')) {
            return $this->passed('http.https', 'The page is served over HTTPS', $c->finalUrl(), $why);
        }

        return $this->finding('http.https', $this->blockerUnlessStaging($c), 'The page is served over plain HTTP', $c->finalUrl(), $why,
            'Install an SSL/TLS certificate (most hosts offer free Let\'s Encrypt certificates) and redirect every http:// address to https:// with a 301.');
    }

    private function httpsRedirect(AuditContext $c): ?Finding
    {
        if (! str_starts_with($c->finalUrl(), 'https://')) {
            return null;
        }

        $httpUrl = 'http://'.substr($c->finalUrl(), strlen('https://'));
        $why = 'Visitors and old links still use http:// addresses. Without a redirect they see an insecure copy or an error, and search engines may find two versions of the page.';
        $result = $c->fetch($httpUrl, ['max_redirects' => 0, 'max_bytes' => 262_144, 'timeout' => 5]);

        if ($result instanceof AuditException) {
            return $result->reason === 'time_budget'
                ? $this->notChecked('http.https_redirect', 'http:// → https:// redirect', $this->failure($result), $why)
                : $this->warning('http.https_redirect', 'The http:// version doesn\'t respond', "{$httpUrl}: {$result->getMessage()}", $why,
                    'Keep port 80 open and redirect every http:// request to the https:// address with a 301.');
        }

        $location = (string) $result->header('location');
        $toHttps = str_starts_with(strtolower($location), 'https://');
        $evidence = "{$httpUrl} → HTTP {$result->status}".($location !== '' ? " to {$location}" : '');

        if ($toHttps && in_array($result->status, [301, 308], true)) {
            return $this->passed('http.https_redirect', 'http:// redirects to https:// permanently', $evidence, $why);
        }

        if ($toHttps) {
            return $this->warning('http.https_redirect', 'http:// redirects to https:// with a temporary redirect', $evidence, $why,
                'Change the http → https redirect to a permanent 301 (or 308) in your server, CDN or hosting settings.');
        }

        return $this->warning('http.https_redirect', 'http:// doesn\'t redirect to https://', $evidence, $why,
            'Redirect every http:// request to the https:// address with a 301 — in your hosting panel, CDN, .htaccess or server configuration.');
    }

    private function stagingHost(AuditContext $c): Finding
    {
        $host = $c->host();
        $looksStaging = StagingHosts::looksLikeStaging($host);
        $why = 'A launch on a staging or temporary platform address usually means the real domain isn\'t connected yet, and staging addresses left in links or settings leak into search results.';

        if ($c->isStaging()) {
            return $this->passed('http.staging_host', $looksStaging ? 'Staging address detected, as expected' : 'Address checked as a staging copy', $host, $why,
                'Nothing to do now. At launch, run this checker again on the production address.');
        }

        if ($looksStaging) {
            return $this->warning('http.staging_host', 'The address looks like a staging or temporary domain', $host, $why,
                'If this is meant to be the public site, connect the production domain and redirect this address to it with a 301. If it is a staging copy, choose "Staging or pre-launch copy" above.',
                'Based on the hostname only, so it can be wrong.');
        }

        return $this->passed('http.staging_host', 'The address doesn\'t look like a staging domain', $host, $why, 'Nothing to do.', 'Based on the hostname only.');
    }
}
