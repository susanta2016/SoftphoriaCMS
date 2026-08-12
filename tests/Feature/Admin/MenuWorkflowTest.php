<?php

namespace Tests\Feature\Admin;

use App\Actions\Menu\CreateMenuItemAction;
use App\Actions\Menu\UpdateMenuItemAction;
use App\Enums\MenuItemDestinationType;
use App\Enums\ModuleKey;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Exceptions\Menu\InvalidMenuItemDestinationException;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Shared\Support\Modules\ModuleNavigationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ADMIN-006 Navigation — destination-exclusivity validation, the
 * Published-only Page constraint, module resolution (the "becomes
 * functional automatically" requirement), hierarchy, and the Page ->
 * Navigation convenience action.
 */
class MenuWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_destination_requires_a_published_page_and_nothing_else(): void
    {
        $admin = $this->admin();
        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);
        $draft = Page::create(['title' => 'Draft', 'slug' => 'draft', 'template' => PageTemplate::Standard->value, 'status' => PageStatus::Draft->value]);

        $this->expectException(InvalidMenuItemDestinationException::class);

        app(CreateMenuItemAction::class)->handle($menu, [
            'label' => 'Draft Page', 'destination_type' => MenuItemDestinationType::Page->value, 'page_id' => $draft->id,
        ], $admin);
    }

    public function test_page_destination_succeeds_for_a_published_page(): void
    {
        $admin = $this->admin();
        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);
        $published = Page::create(['title' => 'About', 'slug' => 'about', 'template' => PageTemplate::About->value, 'status' => PageStatus::Published->value]);

        $item = app(CreateMenuItemAction::class)->handle($menu, [
            'label' => 'About', 'destination_type' => MenuItemDestinationType::Page->value, 'page_id' => $published->id,
        ], $admin);

        $this->assertSame($published->id, $item->page_id);
    }

    public function test_module_destination_rejects_an_unknown_route_key(): void
    {
        $admin = $this->admin();
        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);

        $this->expectException(InvalidMenuItemDestinationException::class);

        app(CreateMenuItemAction::class)->handle($menu, [
            'label' => 'Bogus', 'destination_type' => MenuItemDestinationType::Module->value, 'route_key' => 'not_a_real_module',
        ], $admin);
    }

    public function test_module_destination_does_not_require_the_module_route_to_exist(): void
    {
        $admin = $this->admin();
        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);

        // No 'music.index' route is registered anywhere in this test — the
        // item must still save successfully.
        $item = app(CreateMenuItemAction::class)->handle($menu, [
            'label' => 'Music', 'destination_type' => MenuItemDestinationType::Module->value, 'route_key' => ModuleKey::Music->value,
        ], $admin);

        $this->assertSame('music', $item->route_key);
    }

    public function test_mixing_destination_fields_is_rejected(): void
    {
        $admin = $this->admin();
        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);

        $this->expectException(InvalidMenuItemDestinationException::class);

        app(CreateMenuItemAction::class)->handle($menu, [
            'label' => 'Bad', 'destination_type' => MenuItemDestinationType::Url->value,
            'url' => 'https://example.com', 'route_key' => ModuleKey::Music->value,
        ], $admin);
    }

    public function test_group_destination_requires_no_target_fields(): void
    {
        $admin = $this->admin();
        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);

        $item = app(CreateMenuItemAction::class)->handle($menu, [
            'label' => 'Resources', 'destination_type' => MenuItemDestinationType::Group->value,
        ], $admin);

        $this->assertNull($item->page_id);
        $this->assertNull($item->route_key);
        $this->assertNull($item->url);
    }

    public function test_an_item_cannot_be_set_as_its_own_parent(): void
    {
        $admin = $this->admin();
        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);
        $item = $menu->items()->create(['label' => 'Resources', 'destination_type' => MenuItemDestinationType::Group->value, 'is_enabled' => true]);

        $this->expectException(InvalidArgumentException::class);

        app(UpdateMenuItemAction::class)->handle($item, [
            'label' => 'Resources', 'destination_type' => MenuItemDestinationType::Group->value, 'parent_id' => $item->id,
        ], $admin);
    }

    public function test_hierarchy_is_supported_via_parent_id(): void
    {
        $admin = $this->admin();
        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);

        $parent = app(CreateMenuItemAction::class)->handle($menu, [
            'label' => 'Resources', 'destination_type' => MenuItemDestinationType::Group->value,
        ], $admin);

        $child = app(CreateMenuItemAction::class)->handle($menu, [
            'label' => 'Articles', 'destination_type' => MenuItemDestinationType::Url->value,
            'url' => 'https://example.com/articles', 'parent_id' => $parent->id,
        ], $admin);

        $this->assertTrue($parent->children()->get()->contains($child));
        $this->assertSame($parent->id, $child->parent->id);
    }

    public function test_module_navigation_resolver_returns_null_when_the_module_route_does_not_exist_yet(): void
    {
        $resolver = app(ModuleNavigationResolver::class);

        $this->assertNull($resolver->resolve(ModuleKey::Music));
    }

    public function test_module_navigation_resolver_resolves_a_url_once_the_module_route_exists(): void
    {
        // A fresh route lookup, in its own test — Laravel's RouteCollection
        // lazily caches its name lookup on first Route::has()/route() call,
        // so registering a route mid-test after an earlier resolve() call
        // in the same test is unreliable. This is what proves the "becomes
        // functional automatically" requirement: the same stored
        // route_key='music' now resolves without any menu item edit.
        Route::get('/music', fn () => 'music')->name('music.index');
        Route::getRoutes()->refreshNameLookups();

        $resolver = app(ModuleNavigationResolver::class);

        $this->assertSame(route('music.index'), $resolver->resolve(ModuleKey::Music));
    }

    public function test_add_to_navigation_action_creates_exactly_one_menu_item_through_the_shared_action(): void
    {
        $admin = $this->admin();
        $menu = Menu::create(['name' => 'Primary Navigation', 'slug' => 'primary-navigation', 'is_active' => true]);
        $page = Page::create([
            'title' => 'My Philosophy', 'slug' => 'my-philosophy',
            'template' => PageTemplate::Standard->value, 'status' => PageStatus::Published->value,
        ]);

        Livewire::actingAs($admin)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callFormComponentAction('addToNavigationActions', 'addToNavigation', data: [
                'menu_id' => $menu->id,
                'label' => 'My Philosophy',
            ])
            ->assertHasNoFormComponentActionErrors();

        $this->assertSame(1, $menu->items()->count());
        $item = $menu->items()->first();
        $this->assertSame(MenuItemDestinationType::Page, $item->destination_type);
        $this->assertSame($page->id, $item->page_id);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
