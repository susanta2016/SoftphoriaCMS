<?php

namespace App\Http;

/**
 * softphoria.com is served through Cloudflare, so every request reaches
 * the origin from a Cloudflare address. Trusting exactly these ranges
 * (bootstrap/app.php) lets Laravel read the visitor's IP and https scheme
 * from X-Forwarded-For / X-Forwarded-Proto — rate limits, contact-request
 * IPs and IP geolocation then see the real visitor.
 *
 * Deliberately not '*': the origin server is still reachable directly, and
 * trusting every proxy would let anyone fake their IP with a header.
 *
 * Source: https://www.cloudflare.com/ips-v4 and /ips-v6 (fetched
 * 2026-10-09). Cloudflare announces changes in advance; update both lists
 * when it does.
 */
final class CloudflareProxies
{
    public const RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];
}
