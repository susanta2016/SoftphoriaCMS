<?php

namespace Tests\Feature\Public;

use App\Filament\Resources\Services\Pages\EditService;
use App\Models\Page;
use App\Models\PortfolioItem;
use App\Models\Service;
use App\Shared\Support\Features\Features;
use Database\Seeders\HomePageSeeder;
use Database\Seeders\NavigationMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * The Services phase: the /services listing (primary services grid, the
 * homepage's own "Why Softphoria" and process sections), the single
 * /services/{slug} template (eyebrow, CTAs, capabilities, technologies,
 * related portfolio projects via the shared portfolio_item_service pivot),
 * SEO, sitemap and the admin's related-projects field.
 */
class ServicePagesTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    public function test_the_listing_grid_shows_published_primary_services_linking_to_their_pages(): void
    {
        $primary = $this->service(['title' => 'Web Development', 'slug' => 'web-development', 'summary' => 'Modern, responsive websites.']);
        $secondary = $this->service(['title' => 'Digital Marketing', 'slug' => 'digital-marketing', 'is_featured' => false]);
        $this->service(['title' => 'Draft Service', 'slug' => 'draft-service', 'summary' => 'Not approved yet.', 'is_published' => false]);

        $html = $this->get('/services')->assertOk()->getContent();
        $grid = substr($html, strpos($html, 'id="all-services"'));

        $this->assertStringContainsString('What we do', $html);
        $this->assertStringContainsString('Technology solutions built around your business.', $html);
        $this->assertStringContainsString('Web Development', $grid);
        $this->assertStringContainsString('Modern, responsive websites.', $grid);
        $this->assertStringContainsString('href="'.$primary->url().'"', $grid);
        // A published non-primary service keeps its page and hero quick link, but isn't in the grid.
        $this->assertStringNotContainsString('Digital Marketing', substr($grid, 0, strpos($grid, '</section>')));
        $this->assertStringContainsString('href="'.$secondary->url().'"', $html);
        $this->assertStringNotContainsString('Draft Service', $html);
        $this->assertStringNotContainsString('Not approved yet.', $html);
        $this->assertStringNotContainsString('Everything you need to build, launch and grow', $html);
    }

    public function test_the_listing_and_detail_pages_reuse_the_homepages_own_sections(): void
    {
        $this->seedHomepage();
        $service = Service::query()->published()->firstOrFail();

        $this->get('/services')->assertSeeInOrder(['Our services', 'More than a website.', 'From idea to launch.']);
        $this->get($service->url())->assertSee('From idea to launch.');

        // Edited once in Pages → Home, it changes everywhere.
        $steps = Page::query()->where('slug', 'home')->sole()->sections->first(fn ($section) => ($section->content_json['display'] ?? null) === 'steps');
        $steps->update(['content_json' => [...$steps->content_json, 'heading' => 'Our way of working.']]);

        $this->get('/services')->assertSee('Our way of working.')->assertDontSee('From idea to launch.');
        $this->get($service->url())->assertSee('Our way of working.');

        $steps->update(['is_enabled' => false]);
        $this->get('/services')->assertDontSee('Our way of working.');
    }

    public function test_a_published_service_page_renders_the_hero_breadcrumb_and_ctas(): void
    {
        $service = $this->service(['title' => 'Cloud & DevOps', 'slug' => 'cloud-devops', 'summary' => 'AWS infrastructure and migration.']);

        $response = $this->get('/services/cloud-devops')->assertOk();

        $response->assertSee('aria-label="Breadcrumb"', false);
        $response->assertSeeInOrder(['Home', 'Services', 'Cloud &amp; DevOps'], false);
        $response->assertSeeInOrder(['>Service<', '<h1'], false);
        $response->assertSee('AWS infrastructure and migration.');
        $response->assertSeeInOrder(['href="'.route('contact.index').'"', 'Start a project', 'href="'.route('portfolio.index').'"', 'View portfolio'], false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_unknown_and_unpublished_services_are_not_public(): void
    {
        $this->service(['title' => 'Secret Service', 'slug' => 'secret-service', 'summary' => 'Unapproved copy.', 'is_published' => false]);

        $this->get('/services/does-not-exist')->assertNotFound();
        $this->get('/services/secret-service')->assertNotFound()->assertDontSee('Unapproved copy.');
    }

    public function test_service_pages_have_complete_seo_metadata(): void
    {
        $service = $this->service(['title' => 'Custom Software', 'slug' => 'custom-software', 'summary' => 'Business applications.']);

        $response = $this->get('/services/custom-software');

        $response->assertSee('<title>Custom Software — ', false);
        $response->assertSee('name="description" content="Business applications."', false);
        $response->assertSee('<link rel="canonical" href="'.$service->url().'">', false);
        $response->assertSee('name="robots" content="index, follow"', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('name="twitter:card"', false);
        $response->assertSee('"@type":"Service"', false);

        $service->seo()->create(['robots' => 'noindex, follow']);
        $this->get('/services/custom-software')->assertSee('name="robots" content="noindex, follow"', false);
        $this->get('/services')->assertSee('name="robots" content="index, follow"', false);
    }

    public function test_capabilities_and_technologies_render_only_when_configured(): void
    {
        $this->service(['title' => 'Full', 'slug' => 'full', 'highlights' => [['title' => 'Responsive design', 'description' => 'Works on every device.']], 'technologies' => ['Laravel']]);
        $this->service(['title' => 'Bare', 'slug' => 'bare', 'highlights' => null, 'technologies' => null]);

        $this->get('/services/full')->assertSee('What we deliver')->assertSee('Responsive design')->assertSee('Tech stack')->assertSee('Laravel');
        $this->get('/services/bare')->assertDontSee('What we deliver')->assertDontSee('Tech stack');
    }

    public function test_related_published_portfolio_projects_render_and_others_do_not(): void
    {
        $web = $this->service(['title' => 'Web Development', 'slug' => 'web-development']);
        $cloud = $this->service(['title' => 'Cloud & DevOps', 'slug' => 'cloud-devops']);

        $linked = PortfolioItem::query()->create(['title' => 'B2B E-Commerce Platform', 'is_published' => true]);
        $draft = PortfolioItem::query()->create(['title' => 'Private Client Project', 'is_published' => false]);
        $other = PortfolioItem::query()->create(['title' => 'Cloud Migration', 'is_published' => true]);
        $linked->services()->attach($web);
        $draft->services()->attach($web);
        $other->services()->attach($cloud);

        $response = $this->get('/services/web-development');

        $response->assertSeeInOrder(['Related projects', 'B2B E-Commerce Platform']);
        $response->assertSee('href="'.$linked->url().'"', false);
        $response->assertDontSee('Private Client Project');
        $response->assertDontSee('Cloud Migration');
    }

    public function test_the_related_projects_section_is_hidden_without_projects_or_when_portfolio_is_off(): void
    {
        $web = $this->service(['title' => 'Web Development', 'slug' => 'web-development']);
        $this->get('/services/web-development')->assertDontSee('Related projects');

        PortfolioItem::query()->create(['title' => 'Linked Project', 'is_published' => true])->services()->attach($web);
        app(Features::class)->set('portfolio', false);

        $this->get('/services/web-development')
            ->assertOk()
            ->assertDontSee('Related projects')
            ->assertDontSee('Linked Project')
            ->assertDontSee('View portfolio');
    }

    public function test_the_sitemap_lists_only_published_indexable_services(): void
    {
        $listed = $this->service(['title' => 'Listed', 'slug' => 'listed']);
        $this->service(['title' => 'Draft', 'slug' => 'draft-one', 'is_published' => false]);
        $this->service(['title' => 'Hidden', 'slug' => 'hidden-one'])->seo()->create(['robots' => 'noindex, follow']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('services.index').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$listed->url().'</loc>', $xml);
        $this->assertStringNotContainsString('draft-one', $xml);
        $this->assertStringNotContainsString('hidden-one', $xml);
    }

    public function test_admin_can_link_portfolio_projects_to_a_service_through_the_shared_relationship(): void
    {
        $service = $this->service(['title' => 'API & System Integrations', 'slug' => 'api-system-integrations']);
        $project = PortfolioItem::query()->create(['title' => 'ERP Connector', 'is_published' => true]);
        $alreadyLinked = PortfolioItem::query()->create(['title' => 'Existing Link', 'is_published' => true]);
        $alreadyLinked->services()->attach($service);

        Livewire::actingAs($this->admin())
            ->test(EditService::class, ['record' => $service->getRouteKey()])
            ->assertFormSet(['portfolioItems' => [$alreadyLinked->id]])
            ->fillForm(['portfolioItems' => [$alreadyLinked->id, $project->id], 'is_featured' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        // Visible from the Portfolio side too — one pivot, no duplicate relationship.
        $this->assertSame([$service->id], $project->fresh()->services->pluck('id')->all());
        $this->get('/services/api-system-integrations')->assertSee('ERP Connector')->assertSee('Existing Link');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function service(array $attributes): Service
    {
        return Service::query()->create($attributes + [
            'summary' => 'A summary.',
            'body' => '<p>Body.</p>',
            'icon' => 'code',
            'is_published' => true,
            'is_featured' => true,
        ]);
    }

    private function seedHomepage(): void
    {
        $this->admin();
        $this->seed(NavigationMenuSeeder::class);
        $this->seed(HomePageSeeder::class);
    }
}
