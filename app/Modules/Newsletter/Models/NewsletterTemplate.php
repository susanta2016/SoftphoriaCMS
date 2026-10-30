<?php

namespace App\Modules\Newsletter\Models;

use App\Models\User;
use App\Modules\Newsletter\Enums\TemplateStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable campaign layout. Its HTML is only ever rendered by
 * App\Modules\Newsletter\Support\NewsletterRenderer — plain placeholder
 * substitution from a fixed allowlist, never Blade or PHP.
 */
#[Fillable(['name', 'description', 'subject', 'preheader', 'html_content', 'css_content', 'status', 'created_by', 'updated_by'])]
class NewsletterTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TemplateStatus::class,
        ];
    }

    /**
     * @return HasMany<NewsletterCampaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(NewsletterCampaign::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
