<?php

namespace App\Actions\Menu\Concerns;

use App\Enums\MenuItemDestinationType;
use App\Enums\ModuleKey;
use App\Exceptions\Menu\InvalidMenuItemDestinationException;
use App\Models\Page;

/**
 * Shared by CreateMenuItemAction/UpdateMenuItemAction — the domain-layer
 * authoritative check behind the Filament form's conditional required
 * fields (ADMIN-006 Navigation §F): exactly one of page_id/route_key/url is
 * set, matching destination_type, with Group requiring none of them. A
 * Page destination must currently be Published — Navigation is not allowed
 * to link to a Draft/Scheduled/Archived page.
 */
trait ValidatesMenuItemDestination
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateDestination(array $data): void
    {
        $type = $data['destination_type'] instanceof MenuItemDestinationType
            ? $data['destination_type']
            : MenuItemDestinationType::from($data['destination_type']);

        $pageId = $data['page_id'] ?? null;
        $routeKey = $data['route_key'] ?? null;
        $url = $data['url'] ?? null;

        match ($type) {
            MenuItemDestinationType::Page => $this->validatePage($type, $pageId, $routeKey, $url),
            MenuItemDestinationType::Module => $this->validateModule($type, $pageId, $routeKey, $url),
            MenuItemDestinationType::Url => $this->validateUrl($type, $pageId, $routeKey, $url),
            MenuItemDestinationType::Group => $this->validateGroup($type, $pageId, $routeKey, $url),
        };
    }

    private function validatePage(MenuItemDestinationType $type, mixed $pageId, mixed $routeKey, mixed $url): void
    {
        if (! $pageId || $routeKey || $url) {
            throw InvalidMenuItemDestinationException::forType($type);
        }

        if (! Page::published()->whereKey($pageId)->exists()) {
            throw InvalidMenuItemDestinationException::forUnpublishedPage();
        }
    }

    private function validateModule(MenuItemDestinationType $type, mixed $pageId, mixed $routeKey, mixed $url): void
    {
        if (! $routeKey || $pageId || $url || ModuleKey::tryFrom($routeKey) === null) {
            throw InvalidMenuItemDestinationException::forType($type);
        }
    }

    private function validateUrl(MenuItemDestinationType $type, mixed $pageId, mixed $routeKey, mixed $url): void
    {
        if (! $url || $pageId || $routeKey) {
            throw InvalidMenuItemDestinationException::forType($type);
        }
    }

    private function validateGroup(MenuItemDestinationType $type, mixed $pageId, mixed $routeKey, mixed $url): void
    {
        if ($pageId || $routeKey || $url) {
            throw InvalidMenuItemDestinationException::forType($type);
        }
    }
}
