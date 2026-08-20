<?php

namespace App\Modules\Podcast\Actions\Podcast;

use App\Models\User;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreatePodcastAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): Podcast
    {
        return DB::transaction(function () use ($data, $actor): Podcast {
            $podcast = new Podcast;
            $podcast->fill($data);
            $podcast->status ??= PodcastStatus::Draft;
            $podcast->created_by = $actor->getKey();
            $podcast->updated_by = $actor->getKey();
            $podcast->save();

            $this->auditLog->record($actor, 'podcast.created', $podcast, ['title' => $podcast->title]);

            return $podcast;
        });
    }
}
