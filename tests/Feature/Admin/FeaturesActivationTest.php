<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\FeaturesActivation;
use App\Models\Menu;
use App\Models\PortfolioItem;
use App\Shared\Support\Features\Features;
use Database\Seeders\HomePageSeeder;
use Database\Seeders\NavigationMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * Website Setup → Features Activation and how each switch reaches the
 * public site.
 */
class FeaturesActivationTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    public function test_non_admins_cannot_open_the_page(): void
    {
        $this->actingAs($this->member())->get('/admin/website-setup/features')->assertForbidden();
    }

    public function test_the_page_lists_every_group_feature_and_requirement(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/website-setup/features')
            ->assertOk()
            ->assertSee('Blog System')
            ->assertSee('Blog Settings')
            ->assertSee('Blog configuration including layout, card styles, and display options.')
            ->assertSee('Blog Categories')
            ->assertSee('Requires: Blog Posts')
            ->assertSee('Comment Reporting')
            ->assertSee('Requires: Blog Comments')
            ->assertSee('Website')
            ->assertSee('Contact Widget');
    }

    public function test_everything_is_on_by_default(): void
    {
        $features = app(Features::class);

        foreach (['blog.posts', 'blog.categories', 'blog.tags', 'blog.comments', 'blog.comment_reports', 'blog.reactions', 'portfolio', 'testimonials', 'newsletter', 'contact_widget'] as $key) {
            $this->assertTrue($features->enabled($key), $key);
        }
    }

    public function test_saving_the_page_stores_the_switches(): void
    {
        Livewire::actingAs($this->admin())
            ->test(FeaturesActivation::class)
            ->fillForm(['blog__comments' => false, 'portfolio' => false])
            ->call('save');

        $features = app(Features::class);

        $this->assertFalse($features->enabled('blog.comments'));
        $this->assertFalse($features->enabled('portfolio'));
        $this->assertTrue($features->enabled('blog.posts'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'features.updated']);
    }

    public function test_a_feature_is_off_while_its_requirement_is_off(): void
    {
        $features = app(Features::class);
        $features->set('blog.posts', false);

        $this->assertTrue($features->switchedOn('blog.categories'));
        $this->assertFalse($features->enabled('blog.categories'));
        $this->assertFalse($features->enabled('blog.comment_reports'), 'requirement chain: reports → comments → posts');
    }

    public function test_switching_the_blog_off_404s_it_and_hides_its_menu_links(): void
    {
        $this->admin();
        $this->seed(NavigationMenuSeeder::class);
        $this->livePost(['slug' => 'hello']);

        $this->get('/')->assertSee('href="/blog"', false);
        $this->get('/blog')->assertOk();

        app(Features::class)->set('blog.posts', false);

        $this->get('/blog')->assertNotFound();
        $this->get('/blog/hello')->assertNotFound();
        $this->get('/blog/feed')->assertNotFound();
        $this->get('/')->assertDontSee('href="/blog"', false);
    }

    public function test_archive_switches_404_only_their_own_pages(): void
    {
        $category = $this->category('Cloud');
        $this->livePost(['blog_category_id' => $category->id]);
        $features = app(Features::class);

        $features->set('blog.categories', false);
        $this->get('/blog/category/cloud')->assertNotFound();
        $this->get('/blog')->assertOk()->assertDontSee('/blog/category/cloud', false);
    }

    public function test_website_switches_hide_their_sections_and_widget(): void
    {
        $this->admin();
        $this->seed(HomePageSeeder::class);
        $this->assertGreaterThan(0, PortfolioItem::query()->count());

        $this->get('/')->assertSee('id="portfolio"', false)->assertSee('data-contact-widget', false)->assertSee('footer-newsletter-email', false);

        $features = app(Features::class);
        $features->set('portfolio', false);
        $features->set('contact_widget', false);
        $features->set('newsletter', false);

        $this->get('/')
            ->assertDontSee('id="portfolio"', false)
            ->assertDontSee('data-contact-widget', false)
            ->assertDontSee('footer-newsletter-email', false);

        $this->post('/newsletter/subscribe', ['email' => 'a@example.com'])->assertNotFound();
    }

    public function test_hides_link_only_matches_switched_off_feature_paths(): void
    {
        $features = app(Features::class);
        $features->set('blog.posts', false);

        $this->assertTrue($features->hidesLink('/blog'));
        $this->assertTrue($features->hidesLink('/blog/some-post'));
        $this->assertTrue($features->hidesLink(rtrim(config('app.url'), '/').'/blog'));
        $this->assertFalse($features->hidesLink('https://elsewhere.example/blog'));
        $this->assertFalse($features->hidesLink('/about'));
        $this->assertFalse($features->hidesLink('/#portfolio'));
    }

    public function test_menu_items_to_a_disabled_feature_disappear_from_the_footer_too(): void
    {
        $this->admin();
        $this->seed(NavigationMenuSeeder::class);
        $footer = Menu::query()->where('slug', 'footer-navigation')->sole();
        $this->assertTrue($footer->items()->where('url', '/blog')->exists());

        app(Features::class)->set('blog.posts', false);

        $html = $this->get('/contact')->getContent();
        $this->assertStringNotContainsString('href="/blog"', $html);
    }

    public function test_the_newsletter_honeypot_discards_silently(): void
    {
        $this->from('/')->post('/newsletter/subscribe', ['email' => 'bot@example.com', 'hp_website' => 'x'])
            ->assertSessionHas('newsletter_status');

        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'bot@example.com']);
    }
}
