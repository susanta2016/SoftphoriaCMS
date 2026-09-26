<?php

namespace Tests\Feature\Public;

use App\Models\Page;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Shared\Support\Features\Features;
use Database\Seeders\AboutPageSeeder;
use Database\Seeders\HomePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The "Specialised Expertise" spotlight (AI Development, Cloud & DevOps,
 * Digital Marketing) on the homepage and About page.
 */
class SpecialisedExpertiseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));
    }

    public function test_the_homepage_and_about_page_spotlight_the_three_services_in_order(): void
    {
        $this->seed(HomePageSeeder::class);
        $this->seed(AboutPageSeeder::class);

        foreach (['/', '/about'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $spotlight = substr($html, strpos($html, 'id="specialised-expertise"'));

            $this->assertStringContainsString('Specialised Expertise', $spotlight, $url);
            $this->assertMatchesRegularExpression('/AI Development.*Cloud &amp; DevOps.*Digital Marketing/s', $spotlight, $url);
            foreach (['ai-development', 'cloud-devops', 'digital-marketing'] as $slug) {
                $this->assertStringContainsString(url("/services/{$slug}"), $spotlight, "{$url} links {$slug}");
            }
        }

        $this->get('/services/ai-development')->assertOk()->assertSee('"@type":"FAQPage"', false);
        $this->get('/services/digital-marketing')->assertOk()->assertSee('Search engine optimisation');
    }

    public function test_the_new_services_stay_out_of_the_main_homepage_grid(): void
    {
        $this->seed(HomePageSeeder::class);

        $this->assertFalse(Service::query()->where('slug', 'ai-development')->sole()->is_featured);
        $this->assertSame(6, Service::query()->where('is_featured', true)->count());
    }

    public function test_the_spotlight_hides_with_the_services_feature(): void
    {
        $this->seed(HomePageSeeder::class);
        app(Features::class)->set('services', false);

        $this->get('/')->assertDontSee('id="specialised-expertise"', false);
    }

    public function test_the_migration_adds_services_and_spotlights_once_after_the_right_block(): void
    {
        $this->seed(HomePageSeeder::class);
        $this->seed(AboutPageSeeder::class);

        // Recreate the pre-migration state: no new services, no spotlight sections.
        Service::query()->whereIn('slug', ['ai-development', 'digital-marketing'])->delete();
        foreach (['home', 'about'] as $slug) {
            Page::query()->where('slug', $slug)->sole()->sections()->where('title', 'Specialised Expertise')->delete();
        }

        $migration = require database_path('migrations/2026_09_26_190000_add_specialised_expertise_spotlight.php');
        $migration->up();
        $migration->up();

        $this->assertSame(2, Service::query()->whereIn('slug', ['ai-development', 'digital-marketing'])->count());

        $home = Page::query()->where('slug', 'home')->sole()->sections()->orderBy('sort_order')->pluck('title')->all();
        $this->assertSame(1, count(array_keys($home, 'Specialised Expertise', true)));
        $this->assertSame('Why Softphoria', $home[array_search('Specialised Expertise', $home, true) - 1]);

        $about = Page::query()->where('slug', 'about')->sole()->sections()->orderBy('sort_order')->pluck('title')->all();
        $this->assertSame('Our Values', $about[array_search('Specialised Expertise', $about, true) - 1]);
    }
}
