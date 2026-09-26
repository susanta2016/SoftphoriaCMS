<?php

namespace App\Jobs;

use App\Shared\Services\Geo\IpGeolocator;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Geolocates one IP address. Dispatched with dispatchAfterResponse() from
 * the blog comment/reaction endpoints, so it runs in the same PHP process
 * right after the visitor's response is sent — no queue worker needed, and
 * no delay for the visitor.
 */
class LookupIpLocation
{
    use Dispatchable;

    public function __construct(public readonly ?string $ip) {}

    public function handle(IpGeolocator $geolocator): void
    {
        $geolocator->locate($this->ip);
    }
}
