<?php

namespace App\Modules\Podcast\Providers;

use App\Modules\Podcast\Console\Commands\PublishDuePodcastEpisodesCommand;
use App\Shared\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Registers the Podcast module (Database Specification §5: podcasts,
 * podcast_episodes, podcast_links, podcast_categories, podcast_tags — the
 * first four already migrated in CORE-002's Phase-1 schema pass; the
 * categories/tags pivots were later repointed to episodes by this module's
 * own migration, see database/migrations/) with the platform via
 * config('modules.enabled'), per docs/ARCHITECTURE.md §5. Filament
 * discovers this module's Resources/Pages automatically from
 * app/Modules/Podcast/Filament/{Resources,Pages} (AdminPanelProvider's
 * existing recursive app/Modules/** discovery) — nothing to wire here for
 * that. The module's own scheduled command is registered here instead of
 * bootstrap/app.php so enabling/disabling this module is still the
 * single-line config/modules.php change §5 promises.
 */
class PodcastServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'Podcast';
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([PublishDuePodcastEpisodesCommand::class]);
        }

        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command(PublishDuePodcastEpisodesCommand::class)
                ->everyFiveMinutes();
        });
    }
}
