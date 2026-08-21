<?php

namespace App\Modules\Commerce\Exceptions;

use RuntimeException;

/**
 * Thrown by OrderItem's model-level saving guard (App\Shared\Concerns\BelongsToExactlyOneOf)
 * when a save would leave the record with both album_id and single_id set,
 * or neither. Mirrors App\Modules\Music\Exceptions\InvalidTrackReleaseException
 * — same rule, same reasoning, now shared via the extracted trait.
 */
class InvalidOrderItemPurchasableException extends RuntimeException
{
    public static function mustReferenceExactlyOne(): self
    {
        return new self('An order item must reference exactly one of an Album or a Single — never both, never neither.');
    }
}
