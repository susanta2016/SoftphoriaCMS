<?php

namespace App\Modules\Commerce\Exceptions;

use RuntimeException;

/**
 * Thrown by CreatePendingOrderAction when PurchaseReadiness (see
 * App\Modules\Commerce\Actions\PurchaseReadiness) finds the Album/Single is
 * not currently purchasable — e.g. unpublished, no tracks, or a track
 * missing its audio asset. Server-side enforcement of Master Scope's "do not
 * silently sell an Album where some tracks can't be downloaded," independent
 * of whatever a future frontend does or doesn't check before submitting.
 */
class PurchaseNotReadyException extends RuntimeException
{
    /**
     * @param  array<int, string>  $issues
     */
    public static function forIssues(string $itemLabel, array $issues): self
    {
        return new self("\"{$itemLabel}\" is not currently purchasable: ".implode('; ', $issues));
    }
}
