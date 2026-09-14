<?php

namespace App\Modules\InspirationalResources\Actions;

use App\Enums\EmailRecipientType;
use App\Models\User;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Approving a submission is a review-queue transition only — it never
 * creates or relates the submission to any other module's content. It does
 * make the submission's existing public detail page reachable and sends the
 * "inspirational_resource_published" email, which is why this is the only
 * place in the codebase that ever sends that email: this is the single
 * exclusive writer of ResourceSubmissionStatus::Approved (see
 * ResourceSubmissionResource::approveAction(), its only caller).
 */
class ApproveResourceSubmissionAction
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly TemplatedMailer $mailer,
    ) {}

    public function handle(ResourceSubmission $submission, User $actor): ResourceSubmission
    {
        $submission->status = ResourceSubmissionStatus::Approved;
        $submission->save();

        $this->auditLog->record($actor, 'resource_submission.approved', $submission, ['email' => $submission->email]);

        // A broken SMTP config must never turn a successful approval into a
        // 500 for the admin — the status change is already saved above.
        try {
            $this->mailer->send('inspirational_resource_published', EmailRecipientType::User, $submission->email, [
                'submitter_name' => $submission->name,
                'subject' => $submission->subject ?? '',
                'resource_url' => route('inspirational-resources.show', $submission),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Inspirational resource published notification failed to send', [
                'submission_id' => $submission->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $submission;
    }
}
