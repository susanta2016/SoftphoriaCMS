<?php

namespace App\Modules\InspirationalResources\Providers;

use App\Shared\Support\Modules\ModuleServiceProvider;

/**
 * Registers the Inspirational Resources module (resource_submissions — the
 * only table any application code touches — migrated 2026-08-10, see this
 * module's own database/migrations for the one small additive migration
 * layered on top) with the platform via config('modules.enabled'), per
 * docs/ARCHITECTURE.md §5. Filament discovers this module's Resources/Pages
 * automatically from app/Modules/InspirationalResources/Filament/{Resources,Pages}
 * (AdminPanelProvider's existing recursive app/Modules/** discovery).
 *
 * LEGACY/UNUSED SCHEMA: inspirational_resources and resource_tags (also
 * part of the 2026-08-10 migration batch) are never wired to any
 * application code. Client-confirmed, final: there is no separate public
 * "Inspirational Resource" editorial model, and no editorial conversion
 * into any other module — ResourceSubmission is a pure review queue.
 * Left untouched pending a separate database cleanup decision — do not
 * drop them without explicit instruction.
 */
class InspirationalResourcesServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'Inspirational Resources';
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
