<?php

namespace App\Modules\Podcast\Actions\Podcast;

use App\Models\User;
use App\Modules\Podcast\Exceptions\PodcastInUseException;
use App\Modules\Podcast\Models\Podcast;
use App\Shared\Services\AuditLogService;

class DeletePodcastAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Podcast $podcast, User $actor): void
    {
        $episodeCount = $podcast->episodes()->count();

        if ($episodeCount > 0) {
            throw PodcastInUseException::forPodcast($podcast, $episodeCount);
        }

        $podcast->delete();

        $this->auditLog->record($actor, 'podcast.deleted', $podcast, ['title' => $podcast->title]);
    }
}
