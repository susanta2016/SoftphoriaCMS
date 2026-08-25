<?php

namespace App\Modules\InspirationalResources\Actions;

use App\Models\User;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Services\AuditLogService;

/**
 * Approving a submission is a review-queue transition only — it never
 * creates or publishes anything. See CreatePoetryProseFromSubmissionAction
 * for the separate, optional, explicit step that turns an Approved
 * submission into editorial content.
 */
class ApproveResourceSubmissionAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(ResourceSubmission $submission, User $actor): ResourceSubmission
    {
        $submission->status = ResourceSubmissionStatus::Approved;
        $submission->save();

        $this->auditLog->record($actor, 'resource_submission.approved', $submission, ['email' => $submission->email]);

        return $submission;
    }
}
