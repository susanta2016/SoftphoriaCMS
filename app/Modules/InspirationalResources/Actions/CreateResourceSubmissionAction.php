<?php

namespace App\Modules\InspirationalResources\Actions;

use App\Enums\EmailRecipientType;
use App\Models\Role;
use App\Models\User;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $submission->slug = $this->uniqueSlug($submission->subject ?: $submission->name);
        $submission->save();

        $this->notifyAdmins($submission);

        return $submission;
    }

    /**
     * Mirrors CreatePoetryProseFromSubmissionAction's own uniqueSlug()
     * helper. "submit" is reserved — it's the literal path segment the
     * submission-form page lives at (routes/web.php registers it before
     * the {resourceSubmission:slug} wildcard route), so a submission can
     * never end up parked there.
     */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'resource-submission';
        $slug = $base;
        $suffix = 1;

        while ($slug === 'submit' || ResourceSubmission::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
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
