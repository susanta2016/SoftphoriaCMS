<?php

namespace App\Modules\PoetryProse\Providers;

use App\Shared\Support\Modules\ModuleServiceProvider;

/**
 * Registers the Poetry/Prose module (poetry_prose, poetry_prose_categories,
 * poetry_prose_tags, poetry_prose_collections, poetry_prose_revisions — all
 * migrated 2026-08-10, see the module's own database/migrations for the two
 * small additive migrations layered on top) with the platform via
 * config('modules.enabled'), per docs/ARCHITECTURE.md §5. Filament
 * discovers this module's Resources/Pages automatically from
 * app/Modules/PoetryProse/Filament/{Resources,Pages} (AdminPanelProvider's
 * existing recursive app/Modules/** discovery) — nothing to wire here for
 * that. No scheduled command: Poetry/Prose has no Scheduled lifecycle state
 * (client-confirmed), unlike Music/Podcast.
 *
 * LEGACY/UNUSED SCHEMA: poetry_prose_collection_items (also part of the
 * 2026-08-10 migration batch) is never wired to any application code.
 * Client-confirmed, final: one collection per entry via the plain
 * poetry_prose.collection_id column (see PoetryProse::collection()), not
 * this many-to-many pivot. Left untouched pending a separate database
 * cleanup decision — do not drop it without explicit instruction.
 */
class PoetryProseServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'Poetry/Prose';
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
