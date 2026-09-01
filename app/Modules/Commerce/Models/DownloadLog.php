<?php

namespace App\Modules\Commerce\Models;

use App\Models\Media;
use App\Models\User;
use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Music\Models\Track;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The download audit trail (§16/§22 of the approved brief) — moved from
 * App\Models\UserDownload (table name `user_downloads` unchanged, was never
 * written to by any code path; see the recreate migration for why it's a
 * drop+recreate, not an alter). Written on every outcome, success or denial,
 * by App\Modules\Commerce\Actions\Download\AuthorizeTrackDownloadAction or
 * App\Modules\Podcast\Actions\Download\AuthorizePodcastEpisodeDownloadAction
 * — no other writer. Never surfaces a raw access token: entitlements only
 * ever stores access_token_hash, so there is nothing secret for this table
 * (or DownloadLogResource) to leak.
 *
 * Exactly one of track_id/podcast_episode_id is set per row (see the
 * add_podcast_support_to_user_downloads_table migration) — a Track download
 * always has entitlement_id/access_type of Purchase or Membership; a
 * PodcastEpisode download is always Free with a null entitlement_id.
 */
#[Fillable(['user_id', 'entitlement_id', 'access_type', 'track_id', 'podcast_episode_id', 'media_id', 'status', 'denial_reason', 'ip_address', 'user_agent'])]
class DownloadLog extends Model
{
    protected $table = 'user_downloads';

    public const ?string UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'access_type' => DownloadAccessType::class,
            'status' => DownloadLogStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(Entitlement::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function podcastEpisode(): BelongsTo
    {
        return $this->belongsTo(PodcastEpisode::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
