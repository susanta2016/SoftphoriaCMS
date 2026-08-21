<?php

namespace App\Modules\Commerce\Actions\PurchaseReadiness;

use App\Enums\MediaCategory;
use App\Modules\Commerce\Support\PurchaseReadinessResult;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;

/**
 * §15/§9 of the approved brief: an Album is purchasable only when published,
 * has at least one Track, every Track is published, and every Track has a
 * real audio asset — one bad Track fails the whole Album ("do not silently
 * sell an Album where some tracks can't be downloaded"). Single source of
 * truth — see CheckSingleReadinessAction's docblock for the reuse
 * requirement this satisfies identically.
 */
class CheckAlbumReadinessAction
{
    public function handle(Album $album): PurchaseReadinessResult
    {
        $issues = [];

        if ($album->status !== ReleaseStatus::Published) {
            $issues[] = 'Album is not published.';
        }

        $tracks = $album->tracks;

        if ($tracks->isEmpty()) {
            $issues[] = 'Album has no Tracks.';

            return PurchaseReadinessResult::notReady($issues);
        }

        foreach ($tracks as $track) {
            if ($track->status !== TrackStatus::Published) {
                $issues[] = "Track \"{$track->title}\" is not published.";

                continue;
            }

            if ($track->audio_media_id === null) {
                $issues[] = "Track \"{$track->title}\" has no audio file.";
            } elseif ($track->audio?->category() !== MediaCategory::Audio) {
                $issues[] = "Track \"{$track->title}\"'s audio file is not a valid audio asset.";
            }
        }

        return $issues === [] ? PurchaseReadinessResult::ready() : PurchaseReadinessResult::notReady($issues);
    }
}
