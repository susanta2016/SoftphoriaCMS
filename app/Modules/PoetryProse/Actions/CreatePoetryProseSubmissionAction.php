<?php

namespace App\Modules\PoetryProse\Actions;

use App\Enums\EmailRecipientType;
use App\Models\Role;
use App\Models\User;
use App\Modules\PoetryProse\Models\PoetryProseSubmission;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The public Poetry/Prose "Submit Your Writing" form handler — same shape
 * as Inspirational Resources' CreateResourceSubmissionAction: guest-
 * friendly (user_id nullable), notifies every `admin`-role user, and sends
 * the submitter a "received, pending review" acknowledgement. Mail
 * failures are logged, never surfaced — the submission itself is already
 * saved.
 */
class CreatePoetryProseSubmissionAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $submitter): PoetryProseSubmission
    {
        $submission = new PoetryProseSubmission;
        $submission->fill($data);
        $submission->user_id = $submitter?->getKey();
        $submission->save();

        $this->notifyAdmins($submission);
        $this->notifySubmitter($submission);

        return $submission;
    }

    private function notifyAdmins(PoetryProseSubmission $submission): void
    {
        $adminRole = Role::query()->where('slug', 'admin')->first();

        if ($adminRole === null) {
            return;
        }

        foreach ($adminRole->users as $admin) {
            try {
                $this->mailer->send('poetry_prose_submitted', EmailRecipientType::Admin, $admin->email, [
                    'submitter_name' => $submission->name,
                    'submitter_email' => $submission->email,
                    'subject' => $submission->subject ?? '',
                    'category' => $submission->category,
                ]);
            } catch (Throwable $exception) {
                Log::warning('Poetry/Prose submission admin notification failed to send', [
                    'submission_id' => $submission->id,
                    'admin_id' => $admin->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function notifySubmitter(PoetryProseSubmission $submission): void
    {
        try {
            $this->mailer->send('poetry_prose_submission_pending', EmailRecipientType::User, $submission->email, [
                'submitter_name' => $submission->name,
                'subject' => $submission->subject ?? '',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Poetry/Prose submission acknowledgement email failed to send', [
                'submission_id' => $submission->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
