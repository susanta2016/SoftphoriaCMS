<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A message submitted through the public Contact Us form
 * (database/migrations/2026_09_02_110000_create_contact_submissions_table.php)
 * — always a private administrative record, never rendered at a public
 * URL. Created exclusively by App\Actions\Contact\SubmitContactFormAction;
 * never hand-built in the admin panel (mirrors ResourceSubmission's own
 * reasoning).
 */
#[Fillable(['name', 'email', 'phone', 'message'])]
class ContactSubmission extends Model {}
