<?php

namespace App\Modules\Commerce\Actions\PurchaseReadiness;

use App\Enums\MediaCategory;
use App\Modules\Commerce\Support\PurchaseReadinessResult;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Track;

/**
 * A single Track (whether Album-owned or Single-owned) is individually
 * purchasable only when the track itself is published, has a real audio
 * asset, AND its parent Album/Single is published — mirrors
 * CheckSingleReadinessAction's exact reasoning, applied to one track rather
 * than a whole release. Never checks sibling tracks under the same Album:
 * buying Track C must never be blocked by Track A being incomplete, unlike
 * CheckAlbumReadinessAction's whole-album check.
 */
class CheckTrackReadinessAction
{
    public function handle(Track $track): PurchaseReadinessResult
    {
        $issues = [];

        if ($track->status !== TrackStatus::Published) {
            $issues[] = 'Track is not published.';
        }

        if ($track->audio_media_id === null) {
            $issues[] = 'Track has no audio file.';
        } elseif ($track->audio?->category() !== MediaCategory::Audio) {
            $issues[] = 'Track\'s audio file is not a valid audio asset.';
        }

        $release = $track->release();

        if ($release === null || $release->status !== ReleaseStatus::Published) {
            $issues[] = 'Track\'s Album or Single is not published.';
        }

        return $issues === [] ? PurchaseReadinessResult::ready() : PurchaseReadinessResult::notReady($issues);
    }
}
