<?php

namespace Tests\Feature\Public;

use App\Actions\Page\CreatePageAction;
use App\Actions\Page\UpdatePageAction;
use App\Enums\PageTemplate;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WEB-101 item G — page_redirects rows were already written automatically
 * on a slug change (UpdatePageAction) but never consulted by routing. This
 * covers the new `{page:slug}` route ->missing() fallback
 * (App\Actions\Page\ResolvePageRedirectAction).
 */
class PageRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_an_old_slug_redirects_to_the_pages_current_url(): void
    {
        $page = $this->publishedPage(['title' => 'New Home', 'slug' => 'new-slug']);
        $page->redirects()->create([
            'old_path' => 'old-slug',
            'redirect_type' => '301',
            'is_active' => true,
        ]);

        $response = $this->get('/old-slug');

        $response->assertRedirect('/new-slug');
        $response->assertStatus(301);
    }

    public function test_the_configured_redirect_status_code_is_honored(): void
    {
        $page = $this->publishedPage(['title' => 'New Home', 'slug' => 'new-slug-302']);
        $page->redirects()->create([
            'old_path' => 'old-slug-302',
            'redirect_type' => '302',
            'is_active' => true,
        ]);

        $response = $this->get('/old-slug-302');

        $response->assertStatus(302);
    }

    public function test_a_redirect_to_the_home_pages_slug_goes_to_the_root_url(): void
    {
        $home = $this->publishedPage(['title' => 'Home', 'slug' => 'home']);
        $home->redirects()->create([
            'old_path' => 'old-home-slug',
            'redirect_type' => '301',
            'is_active' => true,
        ]);

        $response = $this->get('/old-home-slug');

        $response->assertRedirect(route('home'));
    }

    public function test_an_inactive_redirect_still_404s(): void
    {
        $page = $this->publishedPage(['title' => 'New Home', 'slug' => 'new-slug-inactive']);
        $page->redirects()->create([
            'old_path' => 'old-slug-inactive',
            'redirect_type' => '301',
            'is_active' => false,
        ]);

        $response = $this->get('/old-slug-inactive');

        $response->assertNotFound();
    }

    public function test_a_redirect_pointing_at_an_unpublished_page_404s_instead_of_leaking_it(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'Draft Target', 'slug' => 'draft-target', 'template' => PageTemplate::Standard->value,
        ], $admin);

        $page->redirects()->create([
            'old_path' => 'old-slug-draft',
            'redirect_type' => '301',
            'is_active' => true,
        ]);

        $response = $this->get('/old-slug-draft');

        $response->assertNotFound();
    }

    public function test_renaming_a_published_page_makes_the_old_slug_redirect_end_to_end(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'About', 'slug' => 'about-original', 'template' => PageTemplate::About->value,
        ], $admin);
        $page->update(['status' => 'published']);

        app(UpdatePageAction::class)->handle($page, ['title' => 'About', 'slug' => 'about-renamed'], $admin);

        $response = $this->get('/about-original');

        $response->assertRedirect('/about-renamed');
        $this->get('/about-renamed')->assertOk();
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
