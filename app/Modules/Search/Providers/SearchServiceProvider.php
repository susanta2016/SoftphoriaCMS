<?php

namespace App\Modules\Search\Providers;

use App\Shared\Support\Modules\ModuleServiceProvider;

/**
 * Registers the Search module (unified site-wide search — App\Modules\
 * Search\Services\SearchService, over the 7 existing public content models
 * via Laravel Scout's "database" driver) with the platform via
 * config('modules.enabled'), per docs/ARCHITECTURE.md §5 ("Search" is one
 * of the originally-reserved module names). No migrations of its own — it
 * reads the existing tables the other modules already migrated.
 */
class SearchServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'Search';
    }
}
