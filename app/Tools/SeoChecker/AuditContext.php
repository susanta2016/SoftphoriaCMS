<?php

namespace App\Tools\SeoChecker;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Throwable;

/**
 * Everything the check groups share for one audit: the fetched page, its
 * parsed HTML, and a bounded way to make follow-up requests — every one
 * through SafeFetcher, at most MAX_REQUESTS per audit, none once the time
 * budget is spent, and each URL fetched at most once.
 */
final class AuditContext
{
    public const MAX_REQUESTS = 24;

    private int $requests = 0;

    /** @var array<string, FetchResult|AuditException> */
    private array $cache = [];

    /** @var array<string, mixed> values one check group leaves for another */
    public array $shared = [];

    public function __construct(
        public readonly string $submittedUrl,
        public readonly string $intent,
        public readonly FetchResult $page,
        public readonly ?PageDocument $doc,
        private readonly SafeFetcher $fetcher,
        private readonly float $deadline,
    ) {}

    public function isStaging(): bool
    {
        return $this->intent === 'staging';
    }

    public function finalUrl(): string
    {
        return $this->page->finalUrl;
    }

    public function host(?string $url = null): string
    {
        return strtolower((string) parse_url($url ?? $this->finalUrl(), PHP_URL_HOST));
    }

    public function origin(): string
    {
        $parts = parse_url($this->finalUrl());

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    public function timeLeft(): float
    {
        return $this->deadline - microtime(true);
    }

    /**
     * A follow-up request. Failures come back as the exception instead of
     * being thrown, so a check can report "not checked" with the reason.
     *
     * @param  array<string, mixed>  $options  SafeFetcher::fetch() options
     */
    public function fetch(string $url, array $options = []): FetchResult|AuditException
    {
        $key = ($options['method'] ?? 'GET').' '.$url.' '.($options['max_redirects'] ?? 5);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        if ($this->timeLeft() < 1 || $this->requests >= self::MAX_REQUESTS) {
            return new AuditException('time_budget');
        }

        $this->requests++;
        $options['timeout'] = min($options['timeout'] ?? 6, max(1, $this->timeLeft()));
        $options['connect_timeout'] = min($options['connect_timeout'] ?? 4, $options['timeout']);

        try {
            return $this->cache[$key] = $this->fetcher->fetch($url, $options);
        } catch (AuditException $e) {
            return $this->cache[$key] = $e;
        }
    }

    public function requestCount(): int
    {
        return $this->requests;
    }

    /**
     * An href/src from the page as an absolute http(s) URL (fragment
     * removed), resolved against <base href> and the final URL.
     */
    public function absolute(?string $href): ?string
    {
        $href = trim((string) $href);

        if ($href === '' || preg_match('#^(?:javascript|mailto|tel|data|sms|ftp|file|blob):#i', $href)) {
            return null;
        }

        try {
            $base = new Uri($this->finalUrl());
            if ($this->doc?->baseHref) {
                $base = UriResolver::resolve($base, new Uri($this->doc->baseHref));
            }
            $uri = UriResolver::resolve($base, new Uri($href))->withFragment('');
        } catch (Throwable) {
            return null;
        }

        return in_array(strtolower($uri->getScheme()), ['http', 'https'], true) && $uri->getHost() !== '' ? (string) $uri : null;
    }

    /** Two URLs equal after normalizing case, default ports and an empty path. */
    public function sameUrl(string $a, string $b): bool
    {
        return $this->comparable($a) === $this->comparable($b);
    }

    private function comparable(string $url): string
    {
        $parts = parse_url($url) ?: [];
        $scheme = strtolower($parts['scheme'] ?? '');
        $port = $parts['port'] ?? null;
        $port = $port === ($scheme === 'https' ? 443 : 80) ? null : $port;

        return $scheme.'://'.strtolower($parts['host'] ?? '').($port ? ':'.$port : '').(($parts['path'] ?? '') ?: '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
