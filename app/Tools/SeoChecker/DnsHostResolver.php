<?php

namespace App\Tools\SeoChecker;

/**
 * Public DNS lookup (A and AAAA). Deliberately not getaddrinfo() alone:
 * /etc/hosts entries are local configuration, not the public internet.
 */
class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $ips = [];

        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip)) {
                $ips[] = $ip;
            }
        }

        if ($ips === []) {
            $ips = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($ips));
    }
}
