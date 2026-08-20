<?php

namespace App\Modules\Music\Actions\Album\Concerns;

use App\Modules\Music\Actions\Concerns\SavesMusicSeo;
use App\Modules\Music\Actions\Concerns\SyncsMusicStreamingLinks;

/**
 * Shared by CreateAlbumAction/UpdateAlbumAction. Streaming links
 * (music_streaming_links) and seo_metadata are both separate tables, not
 * albums columns, so plain fill()/save() never touches them — same
 * reasoning as Podcast's SavesPodcastEpisodeRelations. The actual sync
 * logic lives in SyncsMusicStreamingLinks/SavesMusicSeo, shared with
 * Single's (and, for SEO, Track's) equivalent trait.
 */
trait SavesAlbumRelations
{
    use SavesMusicSeo;
    use SyncsMusicStreamingLinks;
}
