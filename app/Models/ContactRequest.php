<?php

namespace App\Models;

use App\Enums\ContactRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ADMIN-010's canonical Core submission model for the public Contact form
 * (App\Http\Controllers\ContactController). `status`/`resolution_notes`/
 * `assigned_to`/`updated_by` are deliberately excluded from #[Fillable] —
 * they're admin-only workflow fields, never set from the public form (see
 * App\Actions\Contact\SubmitContactRequestAction vs
 * App\Actions\Contact\UpdateContactRequestAction).
 */
#[Fillable(['name', 'email', 'phone', 'subject', 'category', 'message'])]
class ContactRequest extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ContactRequestStatus::class,
        ];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
