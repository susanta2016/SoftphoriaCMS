<?php

namespace App\Modules\Music\Providers;

use App\Modules\Music\Console\Commands\PublishDueMusicReleasesCommand;
use App\Shared\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Registers the Music module (Database Specification §19: albums, singles,
 * tracks, lyrics, song_stories, track_credits, music_streaming_links,
 * music_categories, music_tags — the first five already migrated in
 * CORE-002's Phase-1 schema pass; this module's own migrations under
 * database/migrations/ add the per-track detail columns/tables and repoint
 * the categories/tags pivots to tracks) with the platform via
 * config('modules.enabled'), per docs/ARCHITECTURE.md §5. Mirrors
 * App\Modules\Podcast\Providers\PodcastServiceProvider.
 */
class MusicServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'Music';
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([PublishDueMusicReleasesCommand::class]);
        }

        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command(PublishDueMusicReleasesCommand::class)
                ->everyFiveMinutes();
        });
    }
}
