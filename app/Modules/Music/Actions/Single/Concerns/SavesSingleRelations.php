<?php

namespace App\Modules\Music\Actions\Single\Concerns;

use App\Modules\Music\Actions\Concerns\SavesMusicSeo;
use App\Modules\Music\Actions\Concerns\SyncsMusicStreamingLinks;

/**
 * Shared by CreateSingleAction/UpdateSingleAction. See
 * App\Modules\Music\Actions\Album\Concerns\SavesAlbumRelations — identical
 * reasoning; the actual sync logic lives in the shared
 * SyncsMusicStreamingLinks/SavesMusicSeo traits, not duplicated here.
 */
trait SavesSingleRelations
{
    use SavesMusicSeo;
    use SyncsMusicStreamingLinks;
}
