<?php

namespace Tests\Feature\Public;

use App\Actions\Page\UpdatePageAction;
use App\Enums\MenuItemDestinationType;
use App\Enums\PageSectionType;
use App\Models\BlogPost;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Role;
use App\Models\Testimonial;
use App\Models\User;
use App\Shared\Services\Settings\SettingsRepository;
use Database\Seeders\HomePageSeeder;
use Database\Seeders\NavigationMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WEB-103 — the redesigned Softphoria homepage
 * (docs/Reference UI/develop/home.png), seeded by HomePageSeeder and
 * NavigationMenuSeeder through the same Page/Menu data an admin edits. See
 * HomeController/resources/views/home.blade.php and
 * resources/views/components/site/blocks/*.
 */
class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_returns_200(): void
    {
        $this->seedHomepage();

        $this->get('/')->assertOk();
    }

    public function test_the_hero_renders_eyebrow_heading_highlight_buttons_and_stats(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSeeInOrder([
            'Web · Software · Cloud · Integration',
            'Technology that moves your',
            'business forward.',
            'We design, build and support high-performance websites',
            'Start a Project',
            'Our Services',
            // Each stat's <dt> label precedes its <dd> value in the markup
            // (flex-col-reverse shows the value on top).
            'Years Experience',
            '20+',
            'Projects Delivered',
            '100+',
            'Client Relationships',
            'Long-term',
        ]);
    }

    public function test_every_redesigned_block_renders_in_order(): void
    {
        $this->seedHomepage();
        // Latest Insights shows real blog posts and hides itself without any.
        BlogPost::query()->create(['title' => 'A post', 'slug' => 'a-post', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->get('/')->assertSeeInOrder([
            'Trusted Technologies',
            'Technology solutions built around your business.',
            'More than a website.',
            'Selected projects',
            'Modern tools for modern solutions.',
            'From idea to launch.',
            'Trusted by businesses worldwide.',
            'Ideas, tutorials and technology.',
            'Let&#039;s build something great together.',
        ], false);
    }

    public function test_the_services_block_renders_all_six_cards_with_links(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('id="services"', false);
        foreach (['Web Development', 'Custom Software', 'E-Commerce Solutions', 'Cloud &amp; DevOps', 'API &amp; System Integrations', 'CMS &amp; Content Platforms'] as $title) {
            $response->assertSee($title, false);
        }
        $response->assertSee('View All Services');
        $response->assertSee('Learn More');
    }

    public function test_technology_logos_render_as_inline_brand_svgs_grouped(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('aria-label="Laravel"', false);
        $response->assertSee('fill="#FF2D20"', false);
        $response->assertSeeInOrder(['Backend', 'Frontend', 'Cloud &amp; DevOps', 'Databases'], false);
        $response->assertSee('Kubernetes');
        $response->assertSee('PostgreSQL');
    }

    public function test_the_process_steps_render_numbered_in_order(): void
    {
        $this->seedHomepage();

        $content = $this->get('/')->getContent();

        $this->assertNotFalse($content);
        // Searched from the Process heading on — "Build" is also a Why
        // Softphoria feature title earlier on the page.
        $start = strpos($content, 'From idea to launch.');
        $this->assertNotFalse($start);
        $positions = array_map(fn (string $step) => strpos($content, ">{$step}</h3>", $start), ['Discover', 'Plan', 'Build', 'Deliver']);

        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
        $this->assertStringContainsString('>04</span>', $content);
    }

    public function test_the_testimonials_section_reads_enabled_testimonials_from_the_admin_resource_in_sort_order(): void
    {
        $this->seedHomepage();
        Testimonial::query()->update(['is_enabled' => false]);
        Testimonial::query()->create(['name' => 'Second Client', 'designation' => 'CTO, Beta', 'message' => 'Second quote.', 'sort_order' => 2]);
        Testimonial::query()->create(['name' => 'First Client', 'designation' => 'CEO, Alpha', 'message' => 'First quote.', 'sort_order' => 1]);
        Testimonial::query()->create(['name' => 'Hidden Client', 'message' => 'Hidden quote.', 'is_enabled' => false]);

        $response = $this->get('/');

        $response->assertSeeInOrder(['First quote.', 'First Client', 'CEO, Alpha', 'Second quote.', 'Second Client']);
        $response->assertDontSee('Hidden quote.');
        $response->assertSee('data-testimonial-slider', false);
        $response->assertSee('data-testimonial-next', false);
    }

    public function test_the_testimonials_slider_autoplays_at_the_admin_configured_interval(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');
        $response->assertSee('data-autoplay="6"', false);

        $section = Page::query()->where('slug', 'home')->firstOrFail()->sections()->where('section_type', PageSectionType::Testimonials->value)->firstOrFail();
        $section->update(['content_json' => [...$section->content_json, 'autoplay_seconds' => 0]]);

        $response = $this->get('/');
        $response->assertSee('data-autoplay="0"', false);
    }

    public function test_the_testimonials_section_shows_an_avatar_from_the_media_library(): void
    {
        $this->seedHomepage();
        Testimonial::query()->update(['is_enabled' => false]);
        $media = $this->imageMedia('avatars/jane.jpg');
        Testimonial::query()->create(['name' => 'Jane', 'message' => 'Great work.', 'avatar_media_id' => $media->id]);

        $response = $this->get('/');

        $response->assertSee('avatars/jane.jpg', false);
        // A single testimonial needs no slider controls.
        $response->assertDontSee('data-testimonial-next', false);
    }

    public function test_the_testimonials_section_is_omitted_when_there_are_none(): void
    {
        $this->seedHomepage();
        Testimonial::query()->delete();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Trusted by businesses worldwide.');
    }

    public function test_the_seeder_moves_the_five_real_client_quotes_into_testimonials_without_duplicating(): void
    {
        $this->seedHomepage();
        $this->seed(HomePageSeeder::class);

        $this->assertSame(5, Testimonial::query()->count());
        $this->assertDatabaseHas('testimonials', ['name' => 'Mark F.', 'designation' => 'CEO at Salus Technology Services Ltd']);
        $this->get('/')->assertSee('This guy is amazing!');
    }

    public function test_reseeding_keeps_media_an_admin_picked(): void
    {
        $this->seedHomepage();
        $hero = $this->imageMedia('media/images/hero.png');
        $background = $this->imageMedia('media/images/mountains.png');

        $page = Page::query()->where('slug', 'home')->with('sections')->firstOrFail();
        $sections = $page->sections->sortBy('sort_order')->map(function ($section) use ($hero, $background) {
            $content = $section->content_json;
            match ($section->section_type) {
                PageSectionType::Hero->value => $content['media_id'] = $hero->id,
                PageSectionType::Testimonials->value, PageSectionType::Cta->value => $content['background_media_id'] = $background->id,
                default => null,
            };

            return ['id' => $section->id, 'section_type' => $section->section_type, 'title' => $section->title, 'is_enabled' => $section->is_enabled, 'content_json' => $content];
        })->values()->all();
        app(UpdatePageAction::class)->handle($page, ['title' => 'Home', 'slug' => 'home', 'sections' => $sections], User::query()->firstOrFail());

        $this->seed(HomePageSeeder::class);

        $response = $this->get('/');
        $response->assertSee('media/images/hero.png', false);
        $response->assertSee("background-image: url('".asset('storage/media/images/mountains.png')."')", false);
    }

    public function test_the_header_shows_the_redesigned_nav_and_lets_talk_button(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('Be your tech partner');
        $response->assertSeeInOrder(['Services', 'Solutions', 'Expertise', 'Portfolio', 'About', 'Blog', 'Contact']);
        $response->assertSee('href="/services"', false);
        $response->assertSee("Let's Talk");
        $response->assertSee('href="'.route('contact.index').'"', false);
        // WEB-102's utility bar and phone module were removed by the redesign.
        $response->assertDontSee('Free Consultation');
        $response->assertDontSee('Monalisa Mansion');
    }

    public function test_the_header_button_and_footer_headings_are_editable_in_website_setup(): void
    {
        $this->seedHomepage();
        $settings = app(SettingsRepository::class);
        $settings->set('general', 'header_cta_label', 'Book a Call');
        $settings->set('general', 'header_cta_url', '/book');
        $settings->set('footer', 'social_heading', 'Find Us');
        $settings->set('footer', 'newsletter_heading', 'Stay in the Loop');

        $response = $this->get('/');

        $response->assertSee('Book a Call');
        $response->assertSee('href="/book"', false);
        $response->assertDontSee("Let's Talk");
        $response->assertSee('Find Us');
        $response->assertSee('Stay in the Loop');
    }

    public function test_the_footer_shows_menu_groups_legal_links_and_newsletter(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('Technology solutions for ambitious businesses.');
        $response->assertSeeInOrder(['Services', 'Web Development', 'Company', 'Expertise', 'System Integration', 'Follow Us']);
        $response->assertSeeInOrder(['Privacy Policy', 'Terms of Service', 'Sitemap', 'Cookie Settings']);
        $response->assertSee(route('newsletter.subscribe'), false);
    }

    public function test_the_menu_seeder_replaces_legacy_menus_but_never_an_admins_own(): void
    {
        $legacy = Menu::query()->create(['slug' => 'primary-navigation', 'name' => 'Primary Navigation', 'is_active' => true]);
        $legacy->items()->create(['label' => 'Music', 'destination_type' => MenuItemDestinationType::Url, 'url' => '#', 'sort_order' => 0, 'is_enabled' => true]);
        $custom = Menu::query()->create(['slug' => 'footer-legal', 'name' => 'Footer Legal Links', 'is_active' => true]);
        $custom->items()->create(['label' => 'My Own Link', 'destination_type' => MenuItemDestinationType::Url, 'url' => '/mine', 'sort_order' => 0, 'is_enabled' => true]);

        $this->seed(NavigationMenuSeeder::class);

        $this->assertSame(
            ['Services', 'Solutions', 'Expertise', 'Portfolio', 'About', 'Blog', 'Contact'],
            $legacy->items()->orderBy('sort_order')->pluck('label')->all(),
        );
        $this->assertSame(['My Own Link'], $custom->items()->pluck('label')->all());
    }

    public function test_the_homepage_does_not_show_any_legacy_all_the_things_light_or_jacob_content(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertDontSee('All The Things Light');
        $response->assertDontSee('I AM. WE ARE. IT IS.');
        $response->assertDontSee('Light is our nature');
        $response->assertDontSee("Jacob's words");
        $response->assertDontSee('Latest Community Comments');
        $response->assertDontSee('Join Our Community');
        $response->assertDontSee('Poetry/Prose');
    }

    public function test_the_homepage_seo_metadata_is_present_and_correct(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title>Softphoria', false);
        $response->assertSee('<link rel="canonical"', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('property="og:site_name" content="Softphoria"', false);
        $response->assertSee('name="twitter:card"', false);
        $response->assertSee('application/ld+json', false);
    }

    public function test_the_homepage_remains_indexable(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('name="robots" content="index, follow"', false);
        $response->assertDontSee('noindex');
    }

    public function test_guest_sees_login_and_register_links_on_the_homepage(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee(route('login'), false);
        $response->assertSee(route('register'), false);
    }

    public function test_authenticated_user_sees_profile_and_logout_on_the_homepage(): void
    {
        $this->seedHomepage();
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee(route('account.profile.edit'), false);
        $response->assertSee(route('logout'), false);
        $response->assertDontSee(route('register'), false);
    }

    public function test_mobile_menu_and_header_markup_are_present(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('data-mobile-menu-toggle', false);
        $response->assertSee('data-mobile-menu', false);
        $response->assertSee('data-scroll-to-top', false);
    }

    /**
     * With no "home" Page seeded at all (and nothing else seeded either),
     * the route still resolves and falls back to neutral content instead
     * of erroring.
     */
    public function test_the_homepage_falls_back_gracefully_with_no_seeded_home_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(config('app.name'));
        $response->assertDontSee('All The Things Light');
    }

    private function imageMedia(string $path): Media
    {
        $media = new Media;
        $media->disk = 'public';
        $media->path = $path;
        $media->original_filename = basename($path);
        $media->mime_type = 'image/png';
        $media->size = 1;
        $media->visibility = 'public';
        $media->uploader_id = User::query()->firstOrFail()->id;
        $media->save();

        return $media;
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    /**
     * HomePageSeeder needs an existing admin (or any) user to record as the
     * Page's author — a fresh RefreshDatabase test has none until one is
     * created here first.
     */
    private function seedHomepage(): void
    {
        $this->admin();
        $this->seed(NavigationMenuSeeder::class);
        $this->seed(HomePageSeeder::class);
    }
}
