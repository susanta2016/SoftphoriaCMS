<?php

namespace App\Shared\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Extracted from the "exactly one of N nullable FKs" rule
 * App\Modules\Music\Models\Track::booted() already enforces inline — Commerce's
 * OrderItem and Entitlement need the identical rule against the same set of
 * columns (album_id/single_id/track_id), which crosses the "genuinely reused
 * by two or more things" bar app/Shared/ is gated on (docs/ARCHITECTURE.md
 * §3). Track itself is left as-is (working code, out of scope for this
 * change) — new usages consume this trait instead of copy-pasting the guard
 * a third time. Originally hardcoded to exactly two columns; generalized to N
 * when OrderItem/Entitlement grew a third purchasable option (Track) — every
 * existing two-column consumer keeps working unchanged.
 *
 * A model using this trait must define exactlyOneOfColumns(): the column
 * names, exactly one of which must be non-null, and exactlyOneOfException():
 * the exception instance to throw when the rule is violated.
 */
trait BelongsToExactlyOneOf
{
    protected static function bootBelongsToExactlyOneOf(): void
    {
        static::saving(function (Model $model): void {
            $columns = $model->exactlyOneOfColumns();
            $filled = array_filter($columns, fn (string $column): bool => $model->{$column} !== null);

            if (count($filled) !== 1) {
                throw $model->exactlyOneOfException();
            }
        });
    }

    /**
     * @return array<int, string>
     */
    abstract public function exactlyOneOfColumns(): array;

    abstract public function exactlyOneOfException(): \Throwable;
}
