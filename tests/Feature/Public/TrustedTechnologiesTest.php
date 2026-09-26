<?php

namespace Tests\Feature\Public;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\Role;
use App\Models\User;
use App\Shared\Support\Pages\TechLogos;
use Database\Seeders\HomePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage's "Trusted Technologies" logo strip additions: Shopify,
 * Claude Code, Replit and Lovable.
 */
class TrustedTechnologiesTest extends TestCase
{
    use RefreshDatabase;

    private const array NEW_ICONS = ['shopify', 'claude', 'replit', 'lovable'];

    public function test_the_new_logos_exist_with_brand_paths(): void
    {
        foreach (self::NEW_ICONS as $key) {
            $logo = TechLogos::find($key);

            $this->assertNotNull($logo, "Missing logo: {$key}");
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $logo['color']);
            $this->assertNotSame('', $logo['path']);
        }

        $this->assertSame('Claude Code', TechLogos::find('claude')['label']);
    }

    public function test_the_homepage_strip_shows_the_new_logos(): void
    {
        $this->seedHomepage();

        $this->get('/')->assertOk()->assertSeeInOrder(['Trusted Technologies', 'PostgreSQL', 'Shopify', 'Claude Code', 'Replit', 'Lovable']);
    }

    public function test_the_migration_appends_missing_logos_once_and_keeps_the_rest(): void
    {
        $this->seedHomepage();

        $section = $this->logoSection();
        $content = $section->content_json;
        $content['gallery_items'] = array_values(array_filter(
            $content['gallery_items'],
            fn (array $item): bool => ! in_array($item['icon'], ['claude', 'replit', 'lovable'], true),
        ));
        $section->forceFill(['content_json' => $content])->save();

        $migration = require database_path('migrations/2026_09_26_130000_add_ai_and_shopify_trusted_technologies.php');
        $migration->up();
        $migration->up();

        $icons = array_column($this->logoSection()->content_json['gallery_items'], 'icon');

        $this->assertSame('laravel', $icons[0]);
        foreach (self::NEW_ICONS as $key) {
            $this->assertSame(1, count(array_keys($icons, $key, true)), "{$key} should appear exactly once");
        }
        $this->assertSame(['shopify', 'claude', 'replit', 'lovable'], array_slice($icons, -4));
    }

    private function logoSection(): PageSection
    {
        return Page::query()->where('slug', 'home')->sole()->sections()
            ->where('section_type', 'gallery')
            ->get()
            ->sole(fn (PageSection $section): bool => ($section->content_json['display'] ?? null) === 'logos');
    }

    private function seedHomepage(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        $this->seed(HomePageSeeder::class);
    }
}
