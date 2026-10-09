<?php

namespace App\Tools\SeoChecker;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The only way the checker talks to the network. Every request:
 *
 *   - goes through UrlGuard first (and again for every redirect hop, which
 *     is followed here by hand, never by the HTTP client);
 *   - connects to the exact IP UrlGuard approved (CURLOPT_RESOLVE), so DNS
 *     can't be re-answered with an internal address in between;
 *   - is http/https only, with no proxy, no cookies, no credentials and no
 *     application headers — just a plain, identifiable user agent;
 *   - has short connect/total timeouts and a byte limit enforced while
 *     downloading (the transfer is aborted past it) and again after any
 *     decompression, which is done here with the same limit (no zip bombs).
 */
class SafeFetcher
{
    public const USER_AGENT = 'Mozilla/5.0 (compatible; SoftphoriaSEOChecker/1.0; +https://softphoria.com/tools)';

    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    private const TLS_ERRORS = [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91];

    public function __construct(private readonly UrlGuard $guard) {}

    /**
     * @param  array{method?: string, max_bytes?: int, timeout?: int|float, connect_timeout?: int|float, max_redirects?: int, accept?: string}  $options
     *
     * @throws AuditException
     */
    public function fetch(string $url, array $options = []): FetchResult
    {
        $options += [
            'method' => 'GET',
            'max_bytes' => 2_000_000,
            'timeout' => 8,
            'connect_timeout' => 4,
            'max_redirects' => 5,
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ];

        $redirects = [];
        $seen = [];
        $current = $url;
        $totalMs = 0;

        for ($hop = 0; ; $hop++) {
            try {
                $target = $this->guard->target($current);
            } catch (AuditException $e) {
                throw $hop === 0 || $e->reason === 'dns_failed' ? $e : new AuditException('redirect_blocked');
            }

            if (in_array($target['url'], $seen, true)) {
                throw new AuditException('redirect_loop');
            }
            $seen[] = $target['url'];

            $response = $this->send($target, $options);
            $totalMs += $response['ms'];

            $location = $response['headers']['location'][0] ?? null;

            if (in_array($response['status'], self::REDIRECT_STATUSES, true) && $location !== null && $options['max_redirects'] > 0) {
                if ($hop >= $options['max_redirects']) {
                    throw new AuditException('too_many_redirects');
                }

                $redirects[] = ['url' => $target['url'], 'status' => $response['status']];

                try {
                    $current = (string) UriResolver::resolve(new Uri($target['url']), new Uri(trim($location)));
                } catch (Throwable) {
                    throw new AuditException('redirect_blocked');
                }

                continue;
            }

            return new FetchResult(
                url: $url,
                finalUrl: $target['url'],
                status: $response['status'],
                headers: $response['headers'],
                body: $response['body'],
                redirects: $redirects,
                responseMs: $response['ms'],
                bytes: $response['bytes'],
                truncated: $response['truncated'],
                totalMs: $totalMs,
            );
        }
    }

    /**
     * One request, no redirects.
     *
     * @param  array{url: string, scheme: string, host: string, port: int, ip: string}  $target
     * @param  array<string, mixed>  $options
     * @return array{status: int, headers: array<string, list<string>>, body: string, ms: int, bytes: int, truncated: bool}
     *
     * @throws AuditException
     */
    private function send(array $target, array $options): array
    {
        $max = (int) $options['max_bytes'];
        $exceeded = false;
        $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];
        $host = trim($target['host'], '[]');

        $curl = [
            CURLOPT_PROTOCOLS_STR => 'http,https',
            CURLOPT_REDIR_PROTOCOLS_STR => 'http,https',
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_MAXFILESIZE_LARGE => $max,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($handle, $downloadTotal, $downloaded) use ($max, &$exceeded): int {
                if ($downloaded > $max) {
                    $exceeded = true;

                    return 1; // abort the transfer
                }

                return 0;
            },
        ];
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            $curl[CURLOPT_RESOLVE] = ["{$host}:{$target['port']}:{$ip}"];
        }

        $started = hrtime(true);

        try {
            $response = Http::withOptions([
                'allow_redirects' => false,
                'decode_content' => false,
                'http_errors' => false,
                'verify' => true,
                'proxy' => '',
                'curl' => $curl,
            ])
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => $options['accept'],
                    'Accept-Encoding' => 'gzip, deflate',
                    'Accept-Language' => 'en;q=0.9, *;q=0.5',
                ])
                ->timeout($options['timeout'])
                ->connectTimeout($options['connect_timeout'])
                ->send($options['method'], $target['url']);
        } catch (Throwable $e) {
            throw new AuditException($exceeded ? 'too_large' : $this->reason($e));
        }

        $ms = (int) round((hrtime(true) - $started) / 1e6);
        $headers = [];
        foreach ($response->headers() as $name => $values) {
            $headers[strtolower($name)] = array_values(array_map('strval', (array) $values));
        }

        $raw = $options['method'] === 'HEAD' ? '' : $response->body();
        $bytes = strlen($raw);
        $truncated = $exceeded || $bytes > $max;

        return [
            'status' => $response->status(),
            'headers' => $headers,
            'body' => $truncated ? '' : $this->decode($raw, $headers['content-encoding'][0] ?? null, $max),
            'ms' => $ms,
            'bytes' => $bytes,
            'truncated' => $truncated,
        ];
    }

    /**
     * @throws AuditException
     */
    private function decode(string $raw, ?string $encoding, int $max): string
    {
        $encoding = strtolower(trim((string) $encoding));

        if ($raw === '' || $encoding === '' || $encoding === 'identity') {
            return $raw;
        }

        if (! in_array($encoding, ['gzip', 'x-gzip', 'deflate'], true)) {
            throw new AuditException('decode_failed');
        }

        // Inflated in small steps, stopping as soon as the output passes
        // $max, so a tiny "zip bomb" never expands fully in memory.
        $format = $encoding === 'deflate' ? (ord($raw[0]) === 0x78 ? ZLIB_ENCODING_DEFLATE : ZLIB_ENCODING_RAW) : ZLIB_ENCODING_GZIP;
        $inflate = @inflate_init($format);
        $decoded = '';

        foreach (str_split($raw, 4096) as $chunk) {
            $part = $inflate === false ? false : @inflate_add($inflate, $chunk, ZLIB_SYNC_FLUSH);
            if ($part === false) {
                throw new AuditException('decode_failed');
            }
            $decoded .= $part;
            if (strlen($decoded) > $max) {
                throw new AuditException('too_large');
            }
        }

        return $decoded;
    }

    private function reason(Throwable $e): string
    {
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof GuzzleRequestException || $cause instanceof ConnectException) {
                $errno = (int) ($cause->getHandlerContext()['errno'] ?? 0);

                return match (true) {
                    $errno === 6 => 'dns_failed',
                    $errno === 7 => 'connect_failed',
                    $errno === 28 => 'timeout',
                    $errno === 42, $errno === 63 => 'too_large',
                    $errno === 1 => 'unsupported_scheme',
                    in_array($errno, self::TLS_ERRORS, true) => 'tls_failed',
                    default => $this->reasonFromMessage($cause->getMessage()),
                };
            }
        }

        return $this->reasonFromMessage($e->getMessage());
    }

    private function reasonFromMessage(string $message): string
    {
        return match (true) {
            (bool) preg_match('/timed? ?out/i', $message) => 'timeout',
            (bool) preg_match('/SSL|TLS|certificate/i', $message) => 'tls_failed',
            default => 'connect_failed',
        };
    }
}
