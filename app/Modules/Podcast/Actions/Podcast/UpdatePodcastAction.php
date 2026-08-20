<?php

namespace App\Modules\Podcast\Actions\Podcast;

use App\Models\User;
use App\Modules\Podcast\Models\Podcast;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdatePodcastAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $categoryIds
     * @param  array<int, int>  $tagIds
     */
    public function handle(Podcast $podcast, array $data, array $categoryIds, array $tagIds, User $actor): Podcast
    {
        return DB::transaction(function () use ($podcast, $data, $categoryIds, $tagIds, $actor): Podcast {
            $podcast->fill($data);
            $podcast->updated_by = $actor->getKey();
            $podcast->save();

            $podcast->categories()->sync($categoryIds);
            $podcast->tags()->sync($tagIds);

            $this->auditLog->record($actor, 'podcast.updated', $podcast, ['title' => $podcast->title]);

            return $podcast;
        });
    }
}
