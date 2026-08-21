<?php

namespace App\Shared\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Extracted from the "exactly one of two nullable FKs" rule
 * App\Modules\Music\Models\Track::booted() already enforces inline — Commerce's
 * OrderItem and Entitlement need the identical rule against the same pair of
 * columns (album_id/single_id), which crosses the "genuinely reused by two or
 * more things" bar app/Shared/ is gated on (docs/ARCHITECTURE.md §3). Track
 * itself is left as-is (working code, out of scope for this change) — new
 * usages consume this trait instead of copy-pasting the guard a third time.
 *
 * A model using this trait must define exactlyOneOfColumns(): a two-element
 * array of column names, and exactlyOneOfException(): the exception instance
 * to throw when the rule is violated.
 */
trait BelongsToExactlyOneOf
{
    protected static function bootBelongsToExactlyOneOf(): void
    {
        static::saving(function (Model $model): void {
            [$first, $second] = $model->exactlyOneOfColumns();

            $hasFirst = $model->{$first} !== null;
            $hasSecond = $model->{$second} !== null;

            if ($hasFirst === $hasSecond) {
                throw $model->exactlyOneOfException();
            }
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    abstract public function exactlyOneOfColumns(): array;

    abstract public function exactlyOneOfException(): \Throwable;
}
