<?php

namespace App\Models;

use App\Enums\EmailRecipientType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fixed, seeded registry (docs/ARCHITECTURE.md §16.5) — `notification_key`
 * and `recipient_type` are deliberately excluded from Fillable. Only the
 * copy/enabled-state fields below are admin-editable; new rows are added
 * exclusively by extending EmailTemplateSeeder, never through the admin UI.
 */
#[Fillable(['is_enabled', 'subject', 'html_body', 'text_body'])]
class EmailTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'recipient_type' => EmailRecipientType::class,
            'available_variables' => 'array',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
