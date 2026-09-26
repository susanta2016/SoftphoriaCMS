<?php

namespace Tests\Feature\Public;

use App\Enums\MenuItemDestinationType;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\HomePageSeeder;
use Database\Seeders\NavigationMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The homepage's Featured Portfolio section (PageSectionType::Portfolio)
 * and the "Work" → "Portfolio" rename.
 */
class FeaturedPortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_shows_only_published_featured_items_in_order(): void
    {
        $this->seedHomepage();
        PortfolioItem::query()->delete();

        PortfolioItem::query()->create(['title' => 'Second Project', 'is_featured' => true, 'sort_order' => 2, 'technologies' => ['Django']]);
        PortfolioItem::query()->create(['title' => 'First Project', 'category' => 'Cloud', 'is_featured' => true, 'sort_order' => 1, 'link_url' => 'https://first.example']);
        PortfolioItem::query()->create(['title' => 'Not Featured', 'sort_order' => 0]);
        PortfolioItem::query()->create(['title' => 'Draft Project', 'is_featured' => true, 'is_published' => false]);

        $response = $this->get('/')->assertOk();

        $response->assertSee('id="portfolio"', false);
        $response->assertSee('Featured Portfolio');
        $response->assertSeeInOrder(['First Project', 'Second Project']);
        $response->assertSee('Django');
        $response->assertSeeInOrder(['href="https://first.example"', 'target="_blank" rel="noopener noreferrer"', 'View Case Study'], false);
        $response->assertDontSee('Not Featured');
        $response->assertDontSee('Draft Project');
    }

    public function test_the_section_respects_its_limit(): void
    {
        $this->seedHomepage();
        PortfolioItem::query()->delete();

        foreach (range(1, 8) as $i) {
            PortfolioItem::query()->create(['title' => "Project {$i}", 'is_featured' => true, 'sort_order' => $i]);
        }

        $this->get('/')->assertSee('Project 6')->assertDontSee('Project 7');
    }

    public function test_the_section_is_hidden_when_nothing_is_featured(): void
    {
        $this->seedHomepage();
        PortfolioItem::query()->update(['is_featured' => false]);

        $this->get('/')->assertOk()->assertDontSee('id="portfolio"', false)->assertDontSee('Selected projects');
    }

    public function test_the_seeders_use_portfolio_instead_of_work(): void
    {
        $this->seedHomepage();

        $labels = Menu::query()->where('slug', 'primary-navigation')->sole()->items()->pluck('label');

        $this->assertContains('Portfolio', $labels);
        $this->assertNotContains('Work', $labels);
        $this->assertSame(3, PortfolioItem::query()->featured()->count());
        $this->get('/')->assertSee('href="/#portfolio"', false);
    }

    public function test_the_migration_renames_work_and_moves_featured_work_into_the_portfolio(): void
    {
        $this->seedHomepage();
        PortfolioItem::query()->delete();

        // Recreate the pre-migration state: a "Work" menu item and the old
        // Featured Work gallery section with its typed-in cards.
        $menu = Menu::query()->where('slug', 'primary-navigation')->sole();
        $menu->items()->create(['label' => 'Work', 'destination_type' => MenuItemDestinationType::Url, 'url' => '/#work', 'sort_order' => 99, 'is_enabled' => true]);

        $section = Page::query()->where('slug', 'home')->sole()->sections()->where('section_type', 'portfolio')->sole();
        $section->forceFill([
            'section_type' => 'gallery',
            'title' => 'Featured Work',
            'content_json' => [
                'display' => 'projects',
                'anchor' => 'work',
                'eyebrow' => 'Featured Work',
                'heading' => 'Selected projects',
                'item_link_label' => 'View Case Study',
                'gallery_items' => [
                    ['title' => 'Old Card A', 'description' => 'Card A text.', 'icon' => 'cart', 'url' => '#', 'media_id' => null],
                    ['title' => 'Old Card B', 'description' => 'Card B text.', 'icon' => 'cog', 'url' => 'https://b.example', 'media_id' => null],
                ],
            ],
        ])->save();

        (require database_path('migrations/2026_09_26_120100_rename_work_to_portfolio.php'))->up();

        $this->assertDatabaseHas('menu_items', ['id' => $menu->items()->where('sort_order', 99)->value('id'), 'label' => 'Portfolio', 'url' => '/#portfolio']);
        $this->assertSame(0, DB::table('menu_items')->where('label', 'Work')->count());

        $section->refresh();
        $this->assertSame('portfolio', $section->section_type);
        $this->assertSame('Featured Portfolio', $section->title);
        $this->assertSame('portfolio', $section->content_json['anchor']);
        $this->assertSame('Featured Portfolio', $section->content_json['eyebrow']);
        $this->assertArrayNotHasKey('gallery_items', $section->content_json);

        $items = PortfolioItem::query()->ordered()->get();
        $this->assertSame(['Old Card A', 'Old Card B'], $items->pluck('title')->all());
        $this->assertTrue($items->every(fn (PortfolioItem $item): bool => $item->is_featured && $item->is_published));
        $this->assertNull($items[0]->link_url);
        $this->assertSame('https://b.example', $items[1]->link_url);

        $this->get('/')->assertSeeInOrder(['Old Card A', 'Old Card B']);
    }

    private function seedHomepage(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        $this->seed(NavigationMenuSeeder::class);
        $this->seed(HomePageSeeder::class);
    }
}
