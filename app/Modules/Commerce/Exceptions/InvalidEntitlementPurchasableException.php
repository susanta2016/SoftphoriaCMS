<?php

namespace App\Modules\Commerce\Exceptions;

use RuntimeException;

/**
 * Thrown by Entitlement's model-level saving guard when a save would leave
 * the record with both album_id and single_id set, or neither. Mirrors
 * InvalidOrderItemPurchasableException / Music's InvalidTrackReleaseException.
 */
class InvalidEntitlementPurchasableException extends RuntimeException
{
    public static function mustReferenceExactlyOne(): self
    {
        return new self('An entitlement must reference exactly one of an Album, a Single, or a Track — never more than one, never none.');
    }
}
