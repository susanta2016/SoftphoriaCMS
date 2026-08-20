<?php

namespace App\Modules\Music\Actions\Track;

use App\Models\User;
use App\Modules\Music\Actions\Track\Concerns\SavesTrackRelations;
use App\Modules\Music\Models\Track;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateTrackAction
{
    use SavesTrackRelations;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Track $track, array $data, User $actor): Track
    {
        return DB::transaction(function () use ($track, $data, $actor): Track {
            $track->fill([
                ...collect($data)->except(['release', 'lyrics', 'song_story', 'credits', 'categoryIds', 'tagIds', 'seo'])->all(),
                ...$this->resolveRelease($data),
            ]);
            $track->save();

            $this->saveLyrics($track, $data['lyrics'] ?? []);
            $this->saveSongStory($track, $data['song_story'] ?? []);
            $this->syncCredits($track, $data['credits'] ?? []);
            $this->syncCategories($track, $data['categoryIds'] ?? []);
            $this->syncTags($track, $data['tagIds'] ?? []);
            $this->saveMusicSeo($track, $data['seo'] ?? []);

            $this->auditLog->record($actor, 'track.updated', $track, ['title' => $track->title]);

            return $track;
        });
    }
}
