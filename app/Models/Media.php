<?php

namespace App\Models;

use App\Enums\MediaCategory;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['disk', 'path', 'original_filename', 'mime_type', 'size', 'visibility', 'alt_text'])]
class Media extends Model
{
    use HasPublicId, SoftDeletes;

    protected function casts(): array
    {
        return [
            'variants_generated_at' => 'datetime',
            'variants_failed_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    /**
     * Derived from mime_type rather than stored — see MediaCategory.
     */
    public function category(): ?MediaCategory
    {
        return MediaCategory::fromMimeType($this->mime_type);
    }
}
