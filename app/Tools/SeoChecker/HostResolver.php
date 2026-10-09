<?php

namespace App\Tools\SeoChecker;

/**
 * Resolves a hostname to its IP addresses. Bound to DnsHostResolver; tests
 * bind a fixed map instead, so no test depends on real DNS.
 */
interface HostResolver
{
    /**
     * @return list<string> IPv4 and IPv6 addresses (empty when unresolvable)
     */
    public function resolve(string $host): array;
}
