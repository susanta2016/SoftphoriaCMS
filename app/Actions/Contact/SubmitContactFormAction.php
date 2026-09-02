<?php

namespace App\Actions\Contact;

use App\Enums\EmailRecipientType;
use App\Models\ContactSubmission;
use App\Models\Role;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The public Contact Us form's only entry point (docs/ARCHITECTURE.md
 * §16.5/§16.7 — every module sends email through TemplatedMailer, never a
 * bespoke Mailable). Sends the "contact_form_submitted" Email Template to
 * both the submitter (a receipt/acknowledgement) and every admin-role user
 * — mirrors CreateResourceSubmissionAction's exact admin-resolution
 * pattern (no admin-notification-email setting exists anywhere in this
 * codebase; every `admin`-role user is notified instead). A broken SMTP
 * config must never turn a successful submission into a 500 for the
 * visitor — the submission row is always saved first.
 */
class SubmitContactFormAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): ContactSubmission
    {
        $submission = ContactSubmission::query()->create($data);

        $variables = [
            'name' => $submission->name,
            'email' => $submission->email,
            'phone' => $submission->phone ?? '',
            'message' => $submission->message,
        ];

        $this->notifySubmitter($submission, $variables);
        $this->notifyAdmins($submission, $variables);

        return $submission;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function notifySubmitter(ContactSubmission $submission, array $variables): void
    {
        try {
            $this->mailer->send('contact_form_submitted', EmailRecipientType::User, $submission->email, $variables);
        } catch (Throwable $exception) {
            Log::warning('Contact form submitter receipt email failed to send', [
                'submission_id' => $submission->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function notifyAdmins(ContactSubmission $submission, array $variables): void
    {
        $adminRole = Role::query()->where('slug', 'admin')->first();

        if ($adminRole === null) {
            return;
        }

        foreach ($adminRole->users as $admin) {
            try {
                $this->mailer->send('contact_form_submitted', EmailRecipientType::Admin, $admin->email, $variables);
            } catch (Throwable $exception) {
                Log::warning('Contact form admin notification failed to send', [
                    'submission_id' => $submission->id,
                    'admin_id' => $admin->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
