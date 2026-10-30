<?php

namespace App\Models;

use App\Enums\ContactSubmissionReplyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One admin reply to a ContactSubmission
 * (database/migrations/2026_10_30_100000_create_contact_submission_replies_table.php).
 * Created and updated exclusively by
 * App\Actions\Contact\SendContactSubmissionReplyAction.
 */
#[Fillable([
    'contact_submission_id',
    'sent_by_user_id',
    'recipient_email',
    'subject',
    'message',
    'status',
    'sent_at',
    'failed_at',
    'idempotency_key',
])]
class ContactSubmissionReply extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ContactSubmissionReplyStatus::class,
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ContactSubmission, $this>
     */
    public function contactSubmission(): BelongsTo
    {
        return $this->belongsTo(ContactSubmission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
