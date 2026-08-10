<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Assigns a ULID to the `public_id` column on creation, independent of the
 * auto-increment `id` primary key used for internal FKs and joins.
 */
trait HasPublicId
{
    use HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }
}
