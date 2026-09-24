<?php

namespace App\Modules\PoetryProse\Actions;

use App\Enums\EmailRecipientType;
use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseSubmissionStatus;
use App\Modules\PoetryProse\Models\PoetryProseSubmission;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The admin review-queue transitions (Mark In Review / Approve / Archive).
 * Approving publishes nothing, but it is the only transition that emails
 * the submitter ("poetry_prose_submission_approved") — the "pending review"
 * email goes out once, at submission time (CreatePoetryProseSubmissionAction),
 * never from here. The audit log is where "who reviewed this, and when"
 * lives, same as Inspirational Resources.
 */
class ChangePoetryProseSubmissionStatusAction
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly TemplatedMailer $mailer,
    ) {}

    public function handle(PoetryProseSubmission $submission, PoetryProseSubmissionStatus $status, User $actor): PoetryProseSubmission
    {
        $wasApproved = $submission->status === PoetryProseSubmissionStatus::Approved;

        $submission->status = $status;
        $submission->save();

        $this->auditLog->record($actor, 'poetry_prose_submission.'.$status->value, $submission, ['email' => $submission->email]);

        if ($status === PoetryProseSubmissionStatus::Approved && ! $wasApproved) {
            $this->notifyApproved($submission);
        }

        return $submission;
    }

    /**
     * A broken SMTP config must never turn a successful approval into a 500
     * for the admin — the status change is already saved.
     */
    private function notifyApproved(PoetryProseSubmission $submission): void
    {
        try {
            $this->mailer->send('poetry_prose_submission_approved', EmailRecipientType::User, $submission->email, [
                'submitter_name' => $submission->name,
                'subject' => $submission->subject ?? '',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Poetry/Prose submission approved notification failed to send', [
                'submission_id' => $submission->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
