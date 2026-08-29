<?php

namespace App\Modules\Podcast\Actions\PodcastEpisode;

use App\Models\User;
use App\Modules\Podcast\Actions\PodcastEpisode\Concerns\SavesPodcastEpisodeRelations;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreatePodcastEpisodeAction
{
    use SavesPodcastEpisodeRelations;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): PodcastEpisode
    {
        return DB::transaction(function () use ($data, $actor): PodcastEpisode {
            $episode = new PodcastEpisode;
            $episode->fill(collect($data)->except(['seo', 'categoryIds', 'tagIds'])->all());
            $episode->status ??= PodcastEpisodeStatus::Draft;
            $episode->created_by = $actor->getKey();
            $episode->updated_by = $actor->getKey();
            $episode->save();

            $this->saveSeo($episode, $data['seo'] ?? []);
            $this->syncCategories($episode, $data['categoryIds'] ?? []);
            $this->syncTags($episode, $data['tagIds'] ?? []);

            $this->auditLog->record($actor, 'podcast_episode.created', $episode, ['title' => $episode->title]);

            return $episode;
        });
    }
}
