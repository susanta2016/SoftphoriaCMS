<?php

namespace App\Modules\Music\Actions\Album;

use App\Models\User;
use App\Modules\Music\Actions\Album\Concerns\SavesAlbumRelations;
use App\Modules\Music\Models\Album;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateAlbumAction
{
    use SavesAlbumRelations;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Album $album, array $data, User $actor): Album
    {
        return DB::transaction(function () use ($album, $data, $actor): Album {
            $album->fill(collect($data)->except(['seo'])->all());
            $album->updated_by = $actor->getKey();
            $album->save();

            $this->saveMusicSeo($album, $data['seo'] ?? []);

            $this->auditLog->record($actor, 'album.updated', $album, ['title' => $album->title]);

            return $album;
        });
    }
}
