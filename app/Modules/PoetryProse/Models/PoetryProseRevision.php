<?php

namespace App\Modules\PoetryProse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A snapshot of one PoetryProse save
 * (database/migrations/2026_08_10_100804_create_poetry_prose_revisions_table.php)
 * — mirrors App\Models\PageRevision exactly (no `updated_at` column;
 * snapshot + restore only, no diff/compare UI).
 */
class PoetryProseRevision extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'snapshot_json' => 'array',
        ];
    }

    public function poetryProse(): BelongsTo
    {
        return $this->belongsTo(PoetryProse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
