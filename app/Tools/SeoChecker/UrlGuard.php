<?php

namespace App\Tools\SeoChecker;

/**
 * SSRF guard for every address the checker fetches — the submitted URL,
 * every redirect hop and every follow-up request (robots.txt, sitemap,
 * canonical, images, sampled links).
 *
 *   - http/https only, standard web ports only, no user:password@;
 *   - local-only names (localhost, *.local, *.internal, single labels) refused;
 *   - the host is resolved here and EVERY address it resolves to must be a
 *     public unicast address (IPv4 and IPv6, including IPv4 embedded in
 *     IPv6) — loopback, private, link-local (cloud metadata 169.254.169.254
 *     included), CGNAT, multicast, documentation and reserved ranges are
 *     refused;
 *   - the caller connects to the address checked here (SafeFetcher pins it
 *     with CURLOPT_RESOLVE), so a second DNS answer can't swap in an
 *     internal address between the check and the connection (rebinding).
 */
class UrlGuard
{
    public const MAX_LENGTH = 2048;

    public const ALLOWED_PORTS = [80, 443, 8080, 8443];

    /** Names that only ever mean "this machine / this network". */
    private const LOCAL_SUFFIXES = ['localhost', 'local', 'internal', 'intranet', 'lan', 'home', 'corp', 'localdomain', 'home.arpa', 'test', 'invalid', 'example', 'onion'];

    /** Non-public ranges on top of PHP's own private/reserved filter. */
    private const BLOCKED_CIDRS = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16',
        '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64',
        '2001::/23', '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    public function __construct(private readonly HostResolver $resolver) {}

    /**
     * Turns what a visitor typed into one canonical absolute URL: scheme
     * added when missing (https), lower-case scheme and host, IDN hosts in
     * punycode, default port and fragment removed, "/" as the empty path.
     *
     * @throws AuditException
     */
    public function normalize(string $input): string
    {
        $input = trim($input);

        if ($input === '' || mb_strlen($input) > self::MAX_LENGTH || preg_match('/[\x00-\x20\x7F]/', $input)) {
            throw new AuditException('invalid_url');
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $input)) {
            if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $input) && ! preg_match('#^[^:/]+:\d+#', $input)) {
                throw new AuditException('unsupported_scheme');
            }
            $input = 'https://'.ltrim($input, '/');
        }

        $parts = parse_url($input);

        if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new AuditException('unsupported_scheme');
        }

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new AuditException('invalid_url');
        }

        $scheme = strtolower($parts['scheme']);

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new AuditException('credentials_in_url');
        }

        $host = $this->normalizeHost($parts['host']);

        $port = $parts['port'] ?? null;
        if ($port !== null && ! in_array($port, self::ALLOWED_PORTS, true)) {
            throw new AuditException('port_not_allowed');
        }
        if ($port === ($scheme === 'https' ? 443 : 80)) {
            $port = null;
        }

        $path = $parts['path'] ?? '';
        $path = $path === '' ? '/' : $path;
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        $url = $scheme.'://'.$host.($port !== null ? ':'.$port : '').$path.$query;

        if (strlen($url) > self::MAX_LENGTH) {
            throw new AuditException('invalid_url');
        }

        return $url;
    }

    /**
     * Normalizes, resolves and checks one address; the result says exactly
     * which IP the request must connect to.
     *
     * @return array{url: string, scheme: string, host: string, port: int, ip: string}
     *
     * @throws AuditException
     */
    public function target(string $url): array
    {
        $url = $this->normalize($url);
        $parts = parse_url($url);
        $host = $parts['host'];
        $scheme = $parts['scheme'];
        $bare = trim($host, '[]');

        $ips = filter_var($bare, FILTER_VALIDATE_IP) !== false ? [$bare] : $this->resolver->resolve($host);

        if ($ips === []) {
            throw new AuditException('dns_failed');
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new AuditException('blocked_destination');
            }
        }

        // Prefer IPv4: many hosts have no outbound IPv6 route.
        usort($ips, fn (string $a, string $b): int => str_contains($a, ':') <=> str_contains($b, ':'));

        return [
            'url' => $url,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $parts['port'] ?? ($scheme === 'https' ? 443 : 80),
            'ip' => $ips[0],
        ];
    }

    public function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        $binary = inet_pton($ip);

        // IPv4-mapped / IPv4-compatible IPv6 (::ffff:a.b.c.d, ::a.b.c.d): judge the IPv4 inside.
        if ($binary !== false && strlen($binary) === 16 && str_starts_with($binary, str_repeat("\0", 10))
            && (substr($binary, 10, 2) === "\xff\xff" || substr($binary, 10, 2) === "\0\0")) {
            $v4 = inet_ntop(substr($binary, 12));
            if ($v4 === false || ! $this->isPublicIp($v4)) {
                return false;
            }
        }

        foreach (self::BLOCKED_CIDRS as $cidr) {
            if ($this->inCidr($binary, $cidr)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws AuditException
     */
    private function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim($host, '.'));

        if (str_starts_with($host, '[')) {
            $ip = trim($host, '[]');
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                throw new AuditException('invalid_url');
            }

            return '['.inet_ntop(inet_pton($ip)).']';
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $host;
        }

        if (preg_match('/[^\x20-\x7E]/', $host)) {
            $ascii = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : false;
            if ($ascii === false) {
                throw new AuditException('invalid_url');
            }
            $host = strtolower($ascii);
        }

        if (! preg_match('/^(?=.{1,253}$)([a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?)(\.[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?)+$/', $host)) {
            // Also catches single-label names ("intranet", "router").
            throw new AuditException(str_contains($host, '.') ? 'invalid_url' : 'blocked_destination');
        }

        // Purely numeric labels are not a hostname (e.g. 0x7f.1 or 2130706433 tricks).
        if (preg_match('/^[0-9.]+$/', $host) || preg_match('/(^|\.)0x[0-9a-f]+(\.|$)/', $host)) {
            throw new AuditException('blocked_destination');
        }

        foreach (self::LOCAL_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                throw new AuditException('blocked_destination');
            }
        }

        return $host;
    }

    private function inCidr(string|false $binary, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $net = inet_pton($network);

        if ($binary === false || $net === false || strlen($net) !== strlen($binary)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (substr($binary, 0, $bytes) !== substr($net, 0, $bytes)) {
            return false;
        }

        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($binary[$bytes]) & $mask) === (ord($net[$bytes]) & $mask);
    }
}
