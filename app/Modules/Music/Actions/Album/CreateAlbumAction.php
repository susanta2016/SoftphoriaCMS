<?php

namespace App\Modules\Music\Actions\Album;

use App\Models\User;
use App\Modules\Music\Actions\Album\Concerns\SavesAlbumRelations;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Models\Album;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreateAlbumAction
{
    use SavesAlbumRelations;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): Album
    {
        return DB::transaction(function () use ($data, $actor): Album {
            $album = new Album;
            $album->fill(collect($data)->except(['links', 'seo'])->all());
            $album->status ??= ReleaseStatus::Draft;
            $album->created_by = $actor->getKey();
            $album->updated_by = $actor->getKey();
            $album->save();

            $this->syncStreamingLinks($album, $data['links'] ?? []);
            $this->saveMusicSeo($album, $data['seo'] ?? []);

            $this->auditLog->record($actor, 'album.created', $album, ['title' => $album->title]);

            return $album;
        });
    }
}
