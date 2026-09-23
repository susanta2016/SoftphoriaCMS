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
 * redirects back to the submission form with a generic thank-you message,
 * matching this codebase's other public forms — the new submission isn't
 * publicly visible yet regardless (see ResourceSubmission's Approved-only
 * public listing/detail pages), so there's nothing to send the submitter
 * on to.
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
        // Generated once here, never user-supplied — this is what an
        // Approved submission's public detail page URL uses (see
        // ResourceSubmission::sitemapEntries() / routes/web.php's
        // inspirational-resources.show).
        $submission->slug = ResourceSubmission::uniqueSlug($submission->subject ?: $submission->name);
        $submission->save();

        $this->notifyAdmins($submission);
        $this->notifySubmitter($submission);

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

    /**
     * Sent to the submitter in addition to notifyAdmins() above, never
     * instead of it — deliberately says "pending review", never
     * "published"; ApproveResourceSubmissionAction is the only place that
     * ever sends a "published" email, and only once Approved.
     */
    private function notifySubmitter(ResourceSubmission $submission): void
    {
        try {
            $this->mailer->send('inspirational_resource_pending', EmailRecipientType::User, $submission->email, [
                'submitter_name' => $submission->name,
                'subject' => $submission->subject ?? '',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Inspirational resource submission acknowledgement email failed to send', [
                'submission_id' => $submission->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
