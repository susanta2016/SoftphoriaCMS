<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Behind Cloudflare the visitor's IP and scheme come from X-Forwarded-*,
 * but only when the request really arrives from a Cloudflare address —
 * anyone else's forwarded headers are ignored (no IP spoofing past rate
 * limits).
 */
class CloudflareTrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/client', fn (Request $request): array => [
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
            'host' => $request->getHost(),
        ]);
    }

    public function test_requests_from_cloudflare_use_the_forwarded_visitor_ip_and_scheme(): void
    {
        foreach (['172.68.10.20', '2606:4700:10::6816:1'] as $cloudflare) {
            $this->withServerVariables(['REMOTE_ADDR' => $cloudflare])
                ->withHeaders(['X-Forwarded-For' => '81.2.69.160', 'X-Forwarded-Proto' => 'https'])
                ->getJson('/_test/client')
                ->assertJson(['ip' => '81.2.69.160', 'secure' => true]);
        }
    }

    public function test_a_spoofed_header_sent_through_cloudflare_cannot_hide_the_real_ip(): void
    {
        // The visitor sends their own X-Forwarded-For; Cloudflare appends the real address.
        $this->withServerVariables(['REMOTE_ADDR' => '172.68.10.20'])
            ->withHeaders(['X-Forwarded-For' => '1.2.3.4, 81.2.69.160'])
            ->getJson('/_test/client')
            ->assertJson(['ip' => '81.2.69.160']);
    }

    public function test_forwarded_headers_from_anyone_else_are_ignored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-For' => '81.2.69.160', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example'])
            ->getJson('/_test/client')
            ->assertJson(['ip' => '203.0.113.9', 'secure' => false])
            ->assertJsonMissing(['host' => 'evil.example']);
    }

    public function test_the_forwarded_host_is_never_trusted_even_from_cloudflare(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.68.10.20'])
            ->withHeaders(['X-Forwarded-Host' => 'evil.example'])
            ->getJson('/_test/client')
            ->assertJsonMissing(['host' => 'evil.example']);
    }
}
