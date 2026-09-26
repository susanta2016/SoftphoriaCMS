<?php

namespace Tests\Feature\Public;

use App\Models\IpLocation;
use App\Shared\Services\Geo\IpGeolocator;
use App\Shared\Support\Blog\BlogContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpGeolocatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_private_addresses_are_never_sent_anywhere(): void
    {
        Http::fake();

        $location = app(IpGeolocator::class)->locate('192.168.1.10');

        $this->assertTrue($location->is_private);
        $this->assertSame('Private / local network', $location->summary());
        Http::assertNothingSent();
    }

    public function test_lookups_are_cached_per_ip(): void
    {
        Http::fake(['ipinfo.io/*' => Http::response(['city' => 'Paris', 'region' => 'Île-de-France', 'country' => 'FR', 'loc' => '48.85,2.35'])]);

        $geo = app(IpGeolocator::class);
        $first = $geo->locate('51.15.0.1');
        $geo->locate('51.15.0.1');

        Http::assertSentCount(1);
        $this->assertSame('France', $first->country);
        $this->assertSame('🇫🇷', $first->flag());
        $this->assertStringContainsString('openstreetmap.org', $first->mapUrl());
    }

    public function test_a_failed_lookup_is_stored_and_retried_later(): void
    {
        Http::fake(['ipinfo.io/*' => Http::sequence()->push([], 500)->push(['city' => 'Berlin', 'country' => 'DE'])]);

        $geo = app(IpGeolocator::class);
        $failed = $geo->locate('85.10.0.1');
        $this->assertSame('HTTP 500', $failed->error);
        $this->assertSame('Lookup failed', $failed->summary());

        $this->assertSame('Berlin', $geo->locate('85.10.0.1')->city);
        $this->assertSame(1, IpLocation::query()->count());
    }

    public function test_invalid_input_is_ignored(): void
    {
        Http::fake();

        $this->assertNull(app(IpGeolocator::class)->locate('not-an-ip'));
        $this->assertNull(app(IpGeolocator::class)->locate(null));
    }

    public function test_blog_content_builds_a_table_of_contents_with_unique_ids(): void
    {
        $prepared = BlogContent::prepare('<h2>Intro</h2><p>x</p><h2>Intro</h2><h3>Café &amp; más</h3>');

        $this->assertSame(['intro', 'intro-2', 'cafe-mas'], array_column($prepared['toc'], 'id'));
        $this->assertStringContainsString('<h2 id="intro-2">', $prepared['html']);
        $this->assertStringContainsString('Café', $prepared['html']);
        $this->assertSame(1, BlogContent::readingMinutes('<p>'.str_repeat('word ', 100).'</p>'));
        $this->assertSame(3, BlogContent::readingMinutes('<p>'.str_repeat('word ', 500).'</p>'));
    }
}
