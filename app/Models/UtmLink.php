<?php

namespace App\Models;

use App\Shared\Support\Marketing\UtmParameters;
use App\Shared\Support\Marketing\UtmUrlGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-managed UTM campaign link (Admin → UTM Links). Only the parts are
 * stored; url() builds the shareable link from them. Disabling a link just
 * marks it as no longer in use — visitor capture (CaptureUtmAttribution)
 * records any utm_* parameters it sees, link record or not.
 */
#[Fillable(['name', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'destination', 'is_enabled', 'created_by', 'updated_by'])]
class UtmLink extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function url(): string
    {
        return UtmUrlGenerator::generate((string) $this->destination, $this->only(UtmParameters::KEYS));
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
