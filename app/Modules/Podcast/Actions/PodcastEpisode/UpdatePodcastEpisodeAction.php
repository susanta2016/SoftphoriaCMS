<?php

namespace App\Modules\Podcast\Actions\PodcastEpisode;

use App\Models\User;
use App\Modules\Podcast\Actions\PodcastEpisode\Concerns\SavesPodcastEpisodeRelations;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdatePodcastEpisodeAction
{
    use SavesPodcastEpisodeRelations;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PodcastEpisode $episode, array $data, User $actor): PodcastEpisode
    {
        return DB::transaction(function () use ($episode, $data, $actor): PodcastEpisode {
            $episode->fill(collect($data)->except(['seo', 'categoryIds', 'tagIds'])->all());
            $episode->updated_by = $actor->getKey();
            $episode->save();

            $this->saveSeo($episode, $data['seo'] ?? []);
            $this->syncCategories($episode, $data['categoryIds'] ?? []);
            $this->syncTags($episode, $data['tagIds'] ?? []);
            $this->detectAndSetDuration($episode);

            $this->auditLog->record($actor, 'podcast_episode.updated', $episode, ['title' => $episode->title]);

            return $episode;
        });
    }
}
