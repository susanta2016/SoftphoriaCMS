<?php

namespace Tests\Feature\Admin;

use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ADMIN-006 Pages — CRUD, templates, list/search/filter, and the explicit
 * boundary that creating/editing a Page never touches menu_items.
 */
class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_page_list(): void
    {
        $admin = $this->admin();
        $this->page(['title' => 'About']);

        Livewire::actingAs($admin)
            ->test(ListPages::class)
            ->assertSuccessful()
            ->assertSee('About');
    }

    public function test_non_admin_cannot_access_the_page_list(): void
    {
        $nonAdmin = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($nonAdmin)->get('/admin/pages');

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_page(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->fillForm([
                'title' => 'My Philosophy',
                'slug' => 'my-philosophy',
                'template' => PageTemplate::Standard->value,
                'status' => PageStatus::Draft->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('slug', 'my-philosophy')->firstOrFail();
        $this->assertSame('My Philosophy', $page->title);
        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertSame($admin->id, $page->author_id);
        $this->assertSame($admin->id, $page->created_by);
    }

    public function test_slug_must_be_unique_among_non_deleted_pages(): void
    {
        $admin = $this->admin();
        $this->page(['slug' => 'about']);

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->fillForm([
                'title' => 'About Again',
                'slug' => 'about',
                'template' => PageTemplate::Standard->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_admin_can_edit_a_page(): void
    {
        $admin = $this->admin();
        $page = $this->page(['title' => 'Old Title', 'slug' => 'old-title']);

        Livewire::actingAs($admin)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'New Title'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New Title', $page->fresh()->title);
    }

    public function test_page_templates_are_limited_to_the_closed_phase_1_set(): void
    {
        $this->assertSame(
            ['standard', 'about', 'faq', 'legal', 'contact', 'archive', 'custom'],
            array_column(PageTemplate::cases(), 'value'),
        );
    }

    public function test_creating_a_page_never_creates_a_navigation_item(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->fillForm([
                'title' => 'My Journey',
                'slug' => 'my-journey',
                'template' => PageTemplate::Standard->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(0, MenuItem::query()->count());
    }

    private function page(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'title' => 'Test Page',
            'slug' => 'test-page-'.uniqid(),
            'template' => PageTemplate::Standard->value,
            'status' => PageStatus::Draft->value,
        ], $overrides));
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
