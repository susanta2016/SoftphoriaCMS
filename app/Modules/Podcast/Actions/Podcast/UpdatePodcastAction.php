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
     */
    public function handle(Podcast $podcast, array $data, User $actor): Podcast
    {
        return DB::transaction(function () use ($podcast, $data, $actor): Podcast {
            $podcast->fill($data);
            $podcast->updated_by = $actor->getKey();
            $podcast->save();

            $this->auditLog->record($actor, 'podcast.updated', $podcast, ['title' => $podcast->title]);

            return $podcast;
        });
    }
}
