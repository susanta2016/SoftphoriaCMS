<?php

namespace App\Shared\Support\Modules;

use App\Enums\ModuleKey;
use Illuminate\Support\Facades\Route;

/**
 * Resolves a ModuleKey navigation destination to a real URL, or null if
 * that module hasn't registered its routes yet (ADMIN-006 Navigation). A
 * menu item stores only the key and never needs editing once the module
 * ships — resolution happens here, at render time, not when the item is
 * saved, which is what makes a Music/Community/etc. navigation item
 * "become functional automatically when its corresponding module is
 * implemented" rather than requiring an admin to go back and edit it.
 *
 * Route names below are illustrative until each JACOB-* module actually
 * registers its own routes; this is the single, centralized place that
 * mapping lives, per the "clear module registry/configuration mapping"
 * requirement — never hardcoded inside an individual menu item.
 */
final class ModuleNavigationResolver
{
    public function resolve(ModuleKey $key): ?string
    {
        $routeName = match ($key) {
            ModuleKey::Music => 'music.index',
            ModuleKey::Podcast => 'podcast.index',
            ModuleKey::PoetryProse => 'poetry-prose.index',
            ModuleKey::InspirationalResources => 'inspirational-resources.index',
            ModuleKey::Community => 'community.index',
        };

        return Route::has($routeName) ? route($routeName) : null;
    }
}
