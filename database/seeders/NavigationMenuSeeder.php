<?php

namespace Database\Seeders;

use App\Enums\MenuItemDestinationType;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\SocialLink;
use Illuminate\Database\Seeder;

/**
 * Seeds the two Phase-1 menu locations (ADMIN-006 §J) — identified by
 * `slug`, not a separate `location` column. Stage D looks a menu up by
 * this known slug later. Footer uses the same generic menu system as
 * Primary, not a separate mechanism.
 *
 * Also seeds the footer-navigation menu's default Explore/Community/Support
 * groups (as Group-type items) and their links, plus a starter set of
 * social links — the same content that used to be hardcoded in
 * footer.blade.php, now editable via MenuResource/SocialLinkResource so an
 * admin doesn't land on an empty footer after this migrates.
 */
class NavigationMenuSeeder extends Seeder
{
    public function run(): void
    {
        Menu::query()->firstOrCreate(
            ['slug' => 'primary-navigation'],
            ['name' => 'Primary Navigation', 'is_active' => true],
        );

        $footerMenu = Menu::query()->firstOrCreate(
            ['slug' => 'footer-navigation'],
            ['name' => 'Footer Navigation', 'is_active' => true],
        );

        $this->seedFooterSection($footerMenu, 'Explore', 1, [
            'About', 'Music', 'Podcast', 'Poetry/Prose', 'Inspirational Resources', 'Contact',
        ]);

        $this->seedFooterSection($footerMenu, 'Community', 2, [
            'Latest Comments', 'Join Our Community',
        ]);

        $this->seedFooterSection($footerMenu, 'Support', 3, [
            'Privacy Policy', 'Terms of Use', 'Cookie Policy', 'Contact Us',
        ]);

        foreach (['Facebook', 'Instagram', 'YouTube', 'Twitter'] as $index => $label) {
            SocialLink::query()->firstOrCreate(
                ['label' => $label],
                ['url' => '#', 'sort_order' => $index, 'is_enabled' => true],
            );
        }
    }

    /**
     * @param  array<int, string>  $links
     */
    private function seedFooterSection(Menu $menu, string $heading, int $sortOrder, array $links): void
    {
        $group = MenuItem::query()->firstOrCreate(
            ['menu_id' => $menu->id, 'parent_id' => null, 'label' => $heading],
            ['destination_type' => MenuItemDestinationType::Group, 'sort_order' => $sortOrder, 'is_enabled' => true],
        );

        foreach ($links as $index => $label) {
            MenuItem::query()->firstOrCreate(
                ['menu_id' => $menu->id, 'parent_id' => $group->id, 'label' => $label],
                ['destination_type' => MenuItemDestinationType::Url, 'url' => '#', 'sort_order' => $index, 'is_enabled' => true],
            );
        }
    }
}
