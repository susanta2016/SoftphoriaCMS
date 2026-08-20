<?php

namespace App\Modules\Music\Console\Commands;

use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Shared\Services\AuditLogService;
use Illuminate\Console\Command;

/**
 * Flips Scheduled albums/singles to Published once their publish_at has
 * passed — the Music equivalent of Podcast's
 * PublishDuePodcastEpisodesCommand. Covers both models in one command
 * (rather than two near-identical commands) since they share ReleaseStatus
 * and the same publish_at scheduling shape.
 */
class PublishDueMusicReleasesCommand extends Command
{
    protected $signature = 'music:publish-due-releases';

    protected $description = 'Publish scheduled albums and singles whose publish_at has passed';

    public function handle(AuditLogService $auditLog): int
    {
        $dueAlbums = Album::query()
            ->where('status', ReleaseStatus::Scheduled)
            ->where('publish_at', '<=', now())
            ->get();

        foreach ($dueAlbums as $album) {
            $album->status = ReleaseStatus::Published;
            $album->save();

            $auditLog->record(null, 'album.published', $album, ['title' => $album->title]);
        }

        $dueSingles = Single::query()
            ->where('status', ReleaseStatus::Scheduled)
            ->where('publish_at', '<=', now())
            ->get();

        foreach ($dueSingles as $single) {
            $single->status = ReleaseStatus::Published;
            $single->save();

            $auditLog->record(null, 'single.published', $single, ['title' => $single->title]);
        }

        $this->info("Published {$dueAlbums->count()} due album(s) and {$dueSingles->count()} due single(s).");

        return self::SUCCESS;
    }
}
