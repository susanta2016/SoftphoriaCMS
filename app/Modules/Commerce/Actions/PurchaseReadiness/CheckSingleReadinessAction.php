<?php

namespace App\Modules\Commerce\Actions\PurchaseReadiness;

use App\Enums\MediaCategory;
use App\Modules\Commerce\Support\PurchaseReadinessResult;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Single;

/**
 * §15/§9 of the approved brief: a Single is purchasable only when published,
 * has its Track, that Track is published, and that Track has a real audio
 * asset. The single source of truth — called from CreatePendingOrderAction
 * (hard-blocks order creation) and the Single admin form's readiness panel;
 * never re-implemented at either call site.
 */
class CheckSingleReadinessAction
{
    public function handle(Single $single): PurchaseReadinessResult
    {
        $issues = [];

        if ($single->status !== ReleaseStatus::Published) {
            $issues[] = 'Single is not published.';
        }

        $track = $single->track;

        if ($track === null) {
            $issues[] = 'Single has no Track.';

            return PurchaseReadinessResult::notReady($issues);
        }

        if ($track->status !== TrackStatus::Published) {
            $issues[] = 'Track is not published.';
        }

        if ($track->audio_media_id === null) {
            $issues[] = 'Track has no audio file.';
        } elseif ($track->audio?->category() !== MediaCategory::Audio) {
            $issues[] = 'Track\'s audio file is not a valid audio asset.';
        }

        return $issues === [] ? PurchaseReadinessResult::ready() : PurchaseReadinessResult::notReady($issues);
    }
}
