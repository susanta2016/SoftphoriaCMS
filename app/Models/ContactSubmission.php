<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A message submitted through the public Contact Us form
 * (database/migrations/2026_09_02_110000_create_contact_submissions_table.php)
 * — always a private administrative record, never rendered at a public
 * URL. Created exclusively by App\Actions\Contact\SubmitContactFormAction;
 * never hand-built in the admin panel (mirrors ResourceSubmission's own
 * reasoning). Admin replies live in their own table (replies()); the
 * submission itself is never modified by replying.
 */
#[Fillable(['name', 'email', 'phone', 'message', 'ip_address', 'user_agent'])]
class ContactSubmission extends Model
{
    /**
     * @return HasMany<ContactSubmissionReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ContactSubmissionReply::class)->latest()->latest('id');
    }
}
