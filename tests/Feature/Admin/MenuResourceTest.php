<?php

namespace Tests\Feature\Admin;

use App\Enums\MenuItemDestinationType;
use App\Enums\ModuleKey;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ADMIN-006 Navigation — Menu CRUD and items authored through the Repeater
 * UI, administered entirely separately from Pages (no shared schema/table,
 * no menu UI anywhere on PageResource).
 */
class MenuResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_menu_list(): void
    {
        $admin = $this->admin();
        Menu::create(['name' => 'Primary Navigation', 'slug' => 'primary-navigation', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(ListMenus::class)
            ->assertSuccessful()
            ->assertSee('Primary Navigation');
    }

    public function test_non_admin_cannot_access_the_menu_list(): void
    {
        $nonAdmin = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($nonAdmin)->get('/admin/menus');

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_menu_with_a_url_item(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateMenu::class)
            ->fillForm([
                'name' => 'Footer Navigation',
                'slug' => 'footer-navigation',
                'is_active' => true,
                'items' => [
                    [
                        'label' => 'Partner Website',
                        'destination_type' => MenuItemDestinationType::Url->value,
                        'url' => 'https://example.com',
                        'target' => '_blank',
                        'is_enabled' => true,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $menu = Menu::query()->where('slug', 'footer-navigation')->firstOrFail();
        $this->assertSame(1, $menu->items()->count());
        $this->assertSame('https://example.com', $menu->items()->first()->url);
        $this->assertSame('_blank', $menu->items()->first()->target);
    }

    public function test_admin_can_add_a_page_item_and_a_module_item(): void
    {
        $admin = $this->admin();
        $page = Page::create([
            'title' => 'About', 'slug' => 'about',
            'template' => PageTemplate::About->value, 'status' => PageStatus::Published->value,
        ]);
        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->fillForm([
                'items' => [
                    [
                        'label' => 'About',
                        'destination_type' => MenuItemDestinationType::Page->value,
                        'page_id' => $page->id,
                        'is_enabled' => true,
                    ],
                    [
                        'label' => 'Music',
                        'destination_type' => MenuItemDestinationType::Module->value,
                        'route_key' => ModuleKey::Music->value,
                        'is_enabled' => true,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $menu->refresh();
        $this->assertSame(2, $menu->items()->count());
        $this->assertSame($page->id, $menu->items()->where('label', 'About')->first()->page_id);
        $this->assertSame('music', $menu->items()->where('label', 'Music')->first()->route_key);
    }

    public function test_removing_an_item_from_the_repeater_deletes_it(): void
    {
        $admin = $this->admin();
        $menu = Menu::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);
        $menu->items()->create([
            'label' => 'External', 'destination_type' => MenuItemDestinationType::Url->value,
            'url' => 'https://example.com', 'is_enabled' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->fillForm(['items' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $menu->items()->count());
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
