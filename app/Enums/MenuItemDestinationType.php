<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a menu_items row points at (ADMIN-006 Navigation). Navigation is a
 * separate concern from Pages — a Page never automatically becomes a nav
 * item, and this type never appears on the pages table (no
 * show_in_navigation/navigation_order column exists or should exist there).
 *
 * - Page: menu_items.page_id, an admin-created CMS page (must be Published).
 * - Module: menu_items.route_key, a closed ModuleKey value — never a
 *   free-typed route name, so an admin cannot enter something that later
 *   breaks. Resolved to an actual URL at render time by
 *   App\Shared\Support\Modules\ModuleNavigationResolver, once the target
 *   module exists — the stored item never needs editing when that happens.
 * - Url: menu_items.url, a raw external (or root "/") address.
 * - Group: no target column set — a non-clickable parent/label item that
 *   exists only to hold children (e.g. "Resources" with sub-items).
 */
enum MenuItemDestinationType: string implements HasLabel
{
    case Page = 'page';
    case Module = 'module';
    case Url = 'url';
    case Group = 'group';

    public function getLabel(): string
    {
        return match ($this) {
            self::Page => 'CMS Page',
            self::Module => 'Application/Module Route',
            self::Url => 'External URL',
            self::Group => 'Group (not clickable)',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->getLabel()])
            ->all();
    }
}
