<?php

namespace Tests\Feature\Public;

use App\Actions\Page\CreatePageAction;
use App\Enums\PageTemplate;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WEB-101 item J — the generic page view and the Contact page were rebuilt
 * on the Softphoria design system, but must keep emitting the exact same
 * SEO tag set (App\Shared\Support\Seo\SeoTagBuilder /
 * resources/views/components/seo/head-tags.blade.php): title, description,
 * canonical, robots, Open Graph, Twitter, and JSON-LD.
 */
class SeoMetadataRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_page_emits_the_full_seo_tag_set(): void
    {
        $page = $this->publishedPage([
            'title' => 'SEO Coverage Page',
            'slug' => 'seo-coverage-page',
            'summary' => 'A page used to check SEO tags.',
        ]);

        $response = $this->get('/'.$page->slug);

        $response->assertOk();
        $response->assertSee('<link rel="canonical"', false);
        $response->assertSee('name="robots" content="index, follow"', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('property="og:type"', false);
        $response->assertSee('name="twitter:card"', false);
        $response->assertSee('name="twitter:title"', false);
        $response->assertSee('application/ld+json', false);
    }

    public function test_an_unpublished_preview_page_is_forced_noindex(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'Draft SEO Page', 'slug' => 'draft-seo-page', 'template' => PageTemplate::Standard->value,
        ], $admin);

        $response = $this->actingAs($admin)->get('/admin/pages/'.$page->id.'/preview');

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_the_contact_page_emits_the_full_seo_tag_set_and_is_indexable(): void
    {
        $response = $this->get('/contact');

        $response->assertOk();
        $response->assertSee('<link rel="canonical"', false);
        $response->assertSee('name="robots" content="index, follow"', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('name="twitter:card"', false);
        $response->assertSee('application/ld+json', false);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedPage(array $overrides): Page
    {
        $admin = $this->admin();

        $page = app(CreatePageAction::class)->handle(array_merge([
            'title' => 'Untitled',
            'slug' => 'untitled',
            'template' => PageTemplate::Standard->value,
        ], $overrides), $admin);

        $page->update(['status' => 'published']);

        return $page->fresh();
    }
}
