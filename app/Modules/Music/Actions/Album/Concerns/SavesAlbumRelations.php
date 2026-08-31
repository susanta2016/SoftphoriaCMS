<?php

namespace App\Modules\Music\Actions\Album\Concerns;

use App\Modules\Music\Actions\Concerns\SavesMusicSeo;

/**
 * Shared by CreateAlbumAction/UpdateAlbumAction. seo_metadata is a separate
 * table, not an albums column, so plain fill()/save() never touches it —
 * same reasoning as Podcast's SavesPodcastEpisodeRelations. The actual sync
 * logic lives in SavesMusicSeo, shared with Single's (and, for SEO,
 * Track's) equivalent trait.
 */
trait SavesAlbumRelations
{
    use SavesMusicSeo;
}
