<?php

namespace App\Modules\PoetryProse\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A thematic grouping of Poetry/Prose entries
 * (database/migrations/2026_08_10_100805_create_poetry_prose_collections_table.php),
 * e.g. "Reflections on Grief." One collection per entry (client-confirmed,
 * final) via poetry_prose.collection_id — the schema's own
 * poetry_prose_collection_items many-to-many pivot is left unused.
 */
#[Fillable(['title', 'slug', 'description', 'status'])]
class PoetryProseCollection extends Model
{
    use HasPublicId, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PoetryProseStatus::class,
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PoetryProse::class, 'collection_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
