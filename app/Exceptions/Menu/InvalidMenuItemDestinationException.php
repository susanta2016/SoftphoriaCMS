<?php

namespace App\Exceptions\Menu;

use App\Enums\MenuItemDestinationType;
use RuntimeException;

/**
 * Thrown when a menu item's target fields don't match its destination_type
 * (ADMIN-006 Navigation) — exactly one of page_id/route_key/url may be set,
 * matching the type, and Group items may have none set. This is the
 * domain-layer authoritative check backing the Filament form's conditional
 * required-field validation, the same defense-in-depth shape as
 * MediaCategoryMismatchException for ADMIN-005.
 */
class InvalidMenuItemDestinationException extends RuntimeException
{
    public static function forType(MenuItemDestinationType $type): self
    {
        return new self(match ($type) {
            MenuItemDestinationType::Page => 'A Page destination requires selecting an existing published page.',
            MenuItemDestinationType::Module => 'A Module destination requires selecting a module route key.',
            MenuItemDestinationType::Url => 'A URL destination requires a URL.',
            MenuItemDestinationType::Group => 'A Group item may not have a page, module, or URL destination.',
        });
    }

    public static function forUnpublishedPage(): self
    {
        return new self('Only published pages may be selected as a navigation destination.');
    }
}
