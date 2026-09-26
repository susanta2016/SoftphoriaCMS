<?php

namespace Database\Seeders;

use App\Enums\MenuItemDestinationType;
use App\Models\Menu;
use App\Models\SocialLink;
use Illuminate\Database\Seeder;

/**
 * Seeds the Phase-1 menu locations (ADMIN-006 §J) — identified by `slug`,
 * not a separate `location` column — plus a starter set of social links,
 * so an admin doesn't land on an empty header/footer. Everything here is
 * editable afterwards via MenuResource/SocialLinkResource.
 *
 * WEB-103: the menus follow the Softphoria redesign
 * (docs/Reference UI/develop/home.png) — primary nav, footer
 * Services/Company/Expertise groups, and a new flat `footer-legal` menu for
 * the footer's bottom bar. Homepage blocks are reached via their anchors
 * (e.g. /#services) until dedicated pages exist. A menu still holding the
 * old "All The Things Light" items is replaced once; any other existing
 * menu is left exactly as an admin configured it.
 */
class NavigationMenuSeeder extends Seeder
{
    /**
     * Labels only the legacy Jacob/All The Things Light menus ever had.
     */
    private const array LEGACY_LABELS = ['Music', 'Podcast', 'Poetry/Prose', 'Inspirational Resources', 'Community', 'Latest Comments', 'Join Our Community'];

    public function run(): void
    {
        $primaryMenu = Menu::query()->firstOrCreate(
            ['slug' => 'primary-navigation'],
            ['name' => 'Primary Navigation', 'is_active' => true],
        );

        $footerMenu = Menu::query()->firstOrCreate(
            ['slug' => 'footer-navigation'],
            ['name' => 'Footer Navigation', 'is_active' => true],
        );

        $legalMenu = Menu::query()->firstOrCreate(
            ['slug' => 'footer-legal'],
            ['name' => 'Footer Legal Links', 'is_active' => true],
        );

        if ($this->needsSeeding($primaryMenu)) {
            $this->seedLinks($primaryMenu, null, [
                'Services' => '/services',
                'Solutions' => '/#why-softphoria',
                'Expertise' => '/#technologies',
                'Portfolio' => '/portfolio',
                'About' => '/about',
                'Blog' => '/blog',
                'Contact' => '/contact',
            ]);
        }

        if ($this->needsSeeding($footerMenu)) {
            $this->seedFooterSection($footerMenu, 'Services', 1, [
                'Web Development' => '/services/web-development',
                'Custom Software' => '/services/custom-software',
                'E-Commerce' => '/services/e-commerce-solutions',
                'Cloud & DevOps' => '/services/cloud-devops',
                'API & Integrations' => '/services/api-system-integrations',
            ]);

            $this->seedFooterSection($footerMenu, 'Company', 2, [
                'About' => '/about',
                'Portfolio' => '/portfolio',
                'Blog' => '/blog',
                'Contact' => '/contact',
            ]);

            $this->seedFooterSection($footerMenu, 'Expertise', 3, [
                'Python' => '/#technologies',
                'Django / Flask' => '/#technologies',
                'Laravel' => '/#technologies',
                'AWS' => '/#technologies',
                'DevOps' => '/#technologies',
                'System Integration' => '/#technologies',
            ]);
        }

        if ($this->needsSeeding($legalMenu)) {
            $this->seedLinks($legalMenu, null, [
                'Privacy Policy' => '/privacy-policy',
                'Terms of Service' => '#',
                'Sitemap' => '/sitemap.xml',
            ]);
        }

        foreach (['Facebook', 'Instagram', 'YouTube', 'Twitter'] as $index => $label) {
            SocialLink::query()->firstOrCreate(
                ['label' => $label],
                ['url' => '#', 'sort_order' => $index, 'is_enabled' => true],
            );
        }
    }

    /**
     * True for an empty menu, or one still holding the legacy items — which
     * are cleared here so the Softphoria set replaces them.
     */
    private function needsSeeding(Menu $menu): bool
    {
        $items = $menu->items()->get();

        if ($items->isEmpty()) {
            return true;
        }

        if ($items->pluck('label')->intersect(self::LEGACY_LABELS)->isEmpty()) {
            return false;
        }

        // Children first — parent_id is a self-referencing foreign key.
        $menu->items()->whereNotNull('parent_id')->delete();
        $menu->items()->delete();

        return true;
    }

    /**
     * @param  array<string, string>  $links  label => url
     */
    private function seedFooterSection(Menu $menu, string $heading, int $sortOrder, array $links): void
    {
        $group = $menu->items()->create([
            'parent_id' => null,
            'label' => $heading,
            'destination_type' => MenuItemDestinationType::Group,
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ]);

        $this->seedLinks($menu, $group->id, $links);
    }

    /**
     * @param  array<string, string>  $links  label => url
     */
    private function seedLinks(Menu $menu, ?int $parentId, array $links): void
    {
        $sortOrder = 0;

        foreach ($links as $label => $url) {
            $menu->items()->create([
                'parent_id' => $parentId,
                'label' => $label,
                'destination_type' => MenuItemDestinationType::Url,
                'url' => $url,
                'sort_order' => $sortOrder++,
                'is_enabled' => true,
            ]);
        }
    }
}
