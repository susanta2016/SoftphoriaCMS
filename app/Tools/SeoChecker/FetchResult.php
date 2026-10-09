<?php

namespace App\Tools\SeoChecker;

/**
 * One completed fetch (after any redirects SafeFetcher followed).
 */
final class FetchResult
{
    /**
     * @param  array<string, list<string>>  $headers  lower-case names
     * @param  list<array{url: string, status: int}>  $redirects  each hop before the final URL
     */
    public function __construct(
        public readonly string $url,
        public readonly string $finalUrl,
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly array $redirects,
        public readonly int $responseMs,
        public readonly int $bytes,
        public readonly bool $truncated = false,
        public readonly int $totalMs = 0,
    ) {}

    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];

        return $values === [] ? null : implode(', ', $values);
    }

    /**
     * @return list<string>
     */
    public function headerValues(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function contentType(): ?string
    {
        $type = $this->header('content-type');

        return $type === null ? null : strtolower(trim(explode(';', $type)[0]));
    }

    public function isHtml(): bool
    {
        $type = $this->contentType();

        if ($type === null) {
            return (bool) preg_match('/^\s*(<!doctype html|<html|<head)/i', substr($this->body, 0, 512));
        }

        return in_array($type, ['text/html', 'application/xhtml+xml'], true);
    }
}
