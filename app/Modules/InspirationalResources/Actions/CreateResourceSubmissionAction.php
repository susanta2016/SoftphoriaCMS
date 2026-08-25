<?php

namespace App\Modules\InspirationalResources\Actions;

use App\Enums\EmailRecipientType;
use App\Models\Role;
use App\Models\User;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The public "Inspirational Resources" form handler. Guest-friendly
 * (`user_id` is nullable — same as Order's guest-checkout precedent);
 * never redirects anywhere but back to the same page with a generic
 * thank-you message, matching this codebase's other public forms.
 *
 * No existing "who is the admin" resolution exists anywhere in this
 * codebase (no admin-notification-email setting, no prior Admin-recipient
 * send) — rather than inventing a new settings key, this notifies every
 * user holding the `admin` role, fully derivable from data that already
 * exists.
 */
class CreateResourceSubmissionAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $submitter): ResourceSubmission
    {
        $submission = new ResourceSubmission;
        $submission->fill($data);
        $submission->user_id = $submitter?->getKey();
        $submission->save();

        $this->notifyAdmins($submission);

        return $submission;
    }

    private function notifyAdmins(ResourceSubmission $submission): void
    {
        $adminRole = Role::query()->where('slug', 'admin')->first();

        if ($adminRole === null) {
            return;
        }

        foreach ($adminRole->users as $admin) {
            try {
                $this->mailer->send('inspirational_resource_submitted', EmailRecipientType::Admin, $admin->email, [
                    'submitter_name' => $submission->name,
                    'submitter_email' => $submission->email,
                    'subject' => $submission->subject ?? '',
                    'category' => $submission->category,
                ]);
            } catch (Throwable $exception) {
                Log::warning('Inspirational resource submission admin notification failed to send', [
                    'submission_id' => $submission->id,
                    'admin_id' => $admin->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
