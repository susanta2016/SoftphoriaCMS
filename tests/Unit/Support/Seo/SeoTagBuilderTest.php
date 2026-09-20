<?php

namespace Tests\Unit\Support\Seo;

use App\Shared\Support\Seo\SeoTagBuilder;
use Tests\TestCase;

/**
 * AUTH-001→005 added `$fallbacks['robots']`/ROBOTS_NOINDEX to
 * SeoTagBuilder for the new transactional auth pages — this guards that
 * existing callers (Home/Page/Contact, none of which pass 'robots') keep
 * their unchanged 'index, follow' default.
 */
class SeoTagBuilderTest extends TestCase
{
    public function test_robots_defaults_to_index_follow_when_not_specified(): void
    {
        $seo = SeoTagBuilder::build(null, [
            'title' => 'A Page',
            'canonical' => 'https://example.test/a-page',
        ], []);

        $this->assertSame('index, follow', $seo['robots']);
    }

    public function test_robots_noindex_constant_is_honored_via_fallbacks(): void
    {
        $seo = SeoTagBuilder::build(null, [
            'title' => 'Register',
            'canonical' => 'https://example.test/register',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], []);

        $this->assertSame('noindex, nofollow', $seo['robots']);
    }
}
