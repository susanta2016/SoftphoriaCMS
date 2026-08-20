<?php

namespace App\Modules\Podcast\Console\Commands;

use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Shared\Services\AuditLogService;
use Illuminate\Console\Command;

/**
 * Flips Scheduled episodes to Published once their publish_at has passed —
 * the Podcast equivalent of PublishDuePagesCommand. podcast_episodes.status
 * doesn't change itself just because publish_at elapsed; something has to
 * actually do the transition. Registered against the scheduler by
 * PodcastServiceProvider::boot(), not bootstrap/app.php, so this module
 * stays a single config/modules.php line to enable/disable.
 */
class PublishDuePodcastEpisodesCommand extends Command
{
    protected $signature = 'podcast:publish-due-episodes';

    protected $description = 'Publish scheduled podcast episodes whose publish_at has passed';

    public function handle(AuditLogService $auditLog): int
    {
        $dueEpisodes = PodcastEpisode::query()
            ->where('status', PodcastEpisodeStatus::Scheduled)
            ->where('publish_at', '<=', now())
            ->get();

        foreach ($dueEpisodes as $episode) {
            $episode->status = PodcastEpisodeStatus::Published;
            $episode->save();

            $auditLog->record(null, 'podcast_episode.published', $episode, ['title' => $episode->title]);
        }

        $this->info("Published {$dueEpisodes->count()} due episode(s).");

        return self::SUCCESS;
    }
}
