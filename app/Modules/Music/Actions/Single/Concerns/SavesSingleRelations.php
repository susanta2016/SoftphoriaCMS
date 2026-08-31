<?php

namespace App\Modules\Music\Actions\Single\Concerns;

use App\Modules\Music\Actions\Concerns\SavesMusicSeo;

/**
 * Shared by CreateSingleAction/UpdateSingleAction. See
 * App\Modules\Music\Actions\Album\Concerns\SavesAlbumRelations — identical
 * reasoning; the actual sync logic lives in the shared SavesMusicSeo trait,
 * not duplicated here.
 */
trait SavesSingleRelations
{
    use SavesMusicSeo;
}
