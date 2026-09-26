<?php

namespace Tests\Feature\Public;

use App\Enums\PageSectionType;
use App\Models\Media;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AboutPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The About Us page built from the original softphoria.com/about-us
 * content, and the new section styles it uses (Hero "band", Image + Text
 * "split"/"profile").
 */
class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));
    }

    public function test_the_seeder_builds_the_about_page_with_every_section(): void
    {
        $this->seed(AboutPageSeeder::class);

        $page = Page::query()->where('slug', 'about')->with('sections')->sole();
        $this->assertSame(
            ['hero', 'image_text', 'gallery', 'services', 'gallery', 'image_text', 'cta'],
            $page->sections->sortBy('sort_order')->pluck('section_type')->values()->all(),
        );

        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'the header band provides the only <h1>');
        foreach ([
            'Building powerful web experiences', 'for over 20 years.', 'Our Story', 'digital tools for growth',
            'Innovation First', 'Precision Craftsmanship', 'Client First', 'Results Driven',
            'Our Technical Expertise', 'WooCommerce', 'FastAPI', 'GraphQL', 'MongoDB', 'Webpack',
            'Meet the Founder', 'Susanta Bera', 'Founder &amp; Full-Stack Developer',
            'Let&#039;s build something great together.', 'Get a Free Consultation',
        ] as $text) {
            $this->assertStringContainsString($text, $html, "missing: {$text}");
        }
    }

    public function test_the_original_photos_are_imported_once_with_alt_text(): void
    {
        $this->seed(AboutPageSeeder::class);
        $this->seed(AboutPageSeeder::class);

        $founder = Media::query()->where('original_filename', 'Susanta-Bera.jpeg')->sole();
        $this->assertSame('Susanta Bera, founder of Softphoria', $founder->alt_text);
        Storage::disk($founder->disk)->assertExists($founder->path);
        $this->assertSame(1, Media::query()->where('original_filename', 'softphoria-story.jpg')->count());

        $this->get('/about')->assertSee('alt="Susanta Bera, founder of Softphoria"', false);
    }

    public function test_the_seeder_never_overwrites_an_edited_about_page(): void
    {
        $this->seed(AboutPageSeeder::class);
        $section = Page::query()->where('slug', 'about')->sole()->sections()->where('section_type', PageSectionType::Hero->value)->sole();
        $section->forceFill(['content_json' => [...$section->content_json, 'heading' => 'Our own heading']])->save();

        $this->seed(AboutPageSeeder::class);

        $this->get('/about')->assertSee('Our own heading');
    }

    public function test_the_seeder_replaces_the_placeholder_about_page(): void
    {
        $page = Page::query()->forceCreate(['title' => 'About', 'slug' => 'about', 'template' => 'about', 'status' => 'published']);
        $page->sections()->create(['section_type' => 'rich_text', 'sort_order' => 0, 'is_enabled' => true, 'content_json' => ['body' => '<p>Long Description....</p>']]);

        $this->seed(AboutPageSeeder::class);

        $this->get('/about')->assertDontSee('Long Description')->assertSee('Meet the Founder');
    }

    public function test_pages_without_a_header_band_keep_the_default_title_header(): void
    {
        $page = Page::query()->forceCreate(['title' => 'Plain Page', 'slug' => 'plain', 'template' => 'standard', 'status' => 'published']);
        $page->sections()->create(['section_type' => 'rich_text', 'sort_order' => 0, 'is_enabled' => true, 'content_json' => ['body' => '<p>Hello</p>']]);

        $this->get('/plain')->assertOk()->assertSee('<h1 class="mt-6', false)->assertSee('Plain Page');
    }
}
