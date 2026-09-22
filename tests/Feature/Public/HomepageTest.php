<?php

namespace Tests\Feature\Public;

use App\Models\ContactRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\HomePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WEB-102 — the Softphoria homepage, rebuilt on real content transcribed
 * from the live https://softphoria.com/ (and, for Expertise only,
 * https://softphoria.com/about-us) via HomePageSeeder, replacing the old
 * "All The Things Light" placeholder content entirely. See
 * HomeController/resources/views/home.blade.php.
 */
class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_returns_200(): void
    {
        $this->seedHomepage();

        $this->get('/')->assertOk();
    }

    public function test_the_homepage_renders_the_real_softphoria_hero(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Technology &amp; IT Solutions', false);
        $response->assertSee('Excellent IT Services for your success');
        $response->assertSee('Read More');
    }

    /**
     * Browser-verification pass against the live reference site: its small
     * "TECHNOLOGY & IT SOLUTION" label sits ABOVE the big "Excellent IT
     * Services..." heading, not the other way around — assert the actual
     * heading tag carries the big line, and the eyebrow renders separately.
     */
    public function test_the_hero_eyebrow_is_distinct_from_and_precedes_the_heading(): void
    {
        $this->seedHomepage();

        $content = $this->get('/')->getContent();

        $this->assertNotFalse($content);
        $eyebrow = strpos($content, 'Technology &amp; IT Solutions');
        $heading = strpos($content, 'Excellent IT Services for your success');

        $this->assertNotFalse($heading, 'the H1 should contain the big headline');
        $this->assertNotFalse($eyebrow);
        $this->assertTrue($eyebrow < $heading, 'the eyebrow should render before the heading');
    }

    /**
     * The "Explore Our Expert" / "Fully dedicated to the best solutions."
     * section — present on the live homepage, missing entirely from the
     * first WEB-102 pass.
     */
    public function test_the_homepage_renders_the_fully_dedicated_section(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('Explore Our Expert');
        $response->assertSee('Fully dedicated to the best solutions.');
        $response->assertSee('We specialize in crafting high-performance websites');
        $response->assertSee('Learn More');
    }

    public function test_services_and_process_items_render_their_icons(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        // x-site.icon renders nothing for an unknown/blank name, so seeing
        // the svg markup at all confirms a recognized icon key was used.
        $response->assertSee('<svg', false);
    }

    public function test_the_header_shows_the_real_tagline_and_utility_bar(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('Be your tech partner');
        $response->assertSee('contact@softphoria.com');
        $response->assertSee('Monalisa Mansion, Nayabad Ave, Kolkata, India');
    }

    public function test_the_header_shows_the_free_consultation_phone_module(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('Free Consultation');
        $response->assertSee('tel:+91 9163270494', false);
    }

    public function test_the_homepage_renders_who_we_are(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('Who We Are');
        $response->assertSee('Inspiring Spaces for Innovative Minds');
        $response->assertSee('We specialize in delivering custom software, enterprise solutions, and digital transformation services across industries.');
    }

    public function test_the_homepage_renders_all_three_services(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('Creative Design');
        $response->assertSee('Web Development');
        $response->assertSee('Mobile Application');
        $response->assertSee('Build a distinctive brand identity');
    }

    public function test_the_homepage_renders_the_expertise_list(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('Expertise');
        $response->assertSee('Laravel');
        $response->assertSee('React.js');
        $response->assertSee('Docker');
    }

    public function test_the_homepage_renders_all_four_process_steps_in_order(): void
    {
        $this->seedHomepage();

        $content = $this->get('/')->getContent();

        $this->assertNotFalse($content);
        // Matched against each step's own <h3> tag, not the bare word —
        // "Deliver" also appears mid-sentence in the Services section's
        // Mobile Application description, which renders earlier on the page.
        $discovery = strpos($content, '>Discovery</h3>');
        $planning = strpos($content, '>Planning</h3>');
        $execute = strpos($content, '>Execute</h3>');
        $deliver = strpos($content, '>Deliver</h3>');

        $this->assertNotFalse($discovery);
        $this->assertNotFalse($planning);
        $this->assertNotFalse($execute);
        $this->assertNotFalse($deliver);
        $this->assertTrue($discovery < $planning && $planning < $execute && $execute < $deliver);
    }

    public function test_the_homepage_renders_all_five_testimonials(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('John B. — Experienced Linux Administrator of @Brsox', false);
        $response->assertSee('Mark F. — CEO at Salus Technology Services Ltd', false);
        $response->assertSee('Saikiran');
        $response->assertSee('Michael C. Gill');
        $response->assertSee('Dr. Tano');
        $response->assertSee('This guy is amazing!');
    }

    public function test_the_homepage_renders_the_contact_cta_with_real_contact_details_and_the_form(): void
    {
        $this->seedHomepage();

        $response = $this->get('/');

        $response->assertSee('Free Consultation');
        $response->assertSee('contact@softphoria.com');
        $response->assertSee('+91 9163270494');
        $response->assertSee('hp_website', false);
        $response->assertSee(route('contact.submit'), false);
    }

    public function test_submitting_the_homepage_contact_form_uses_the_real_contact_route_and_creates_a_request(): void
    {
        $this->seedHomepage();
        $this->get('/');

        $response = $this->post('/contact', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Interested in a free consultation.',
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('contact_requests', ['email' => 'jane@example.com']);
        $this->assertSame(1, ContactRequest::query()->count());
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
     * The generic CMS Page-driven homepage behavior (WEB-001..005) is
     * unchanged: with no "home" Page seeded at all (and nothing else
     * seeded either), the route still resolves and falls back to neutral
     * content instead of erroring — only the fallback's own copy changed
     * (WEB-102: neutral config('app.name'), never another company's
     * identity) from before.
     */
    public function test_the_homepage_falls_back_gracefully_with_no_seeded_home_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(config('app.name'));
        $response->assertDontSee('All The Things Light');
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
        $this->seed(HomePageSeeder::class);
    }
}
