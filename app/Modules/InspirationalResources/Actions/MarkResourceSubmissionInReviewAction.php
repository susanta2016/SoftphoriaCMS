<?php

namespace App\Modules\InspirationalResources\Actions;

use App\Models\User;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Services\AuditLogService;

class MarkResourceSubmissionInReviewAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(ResourceSubmission $submission, User $actor): ResourceSubmission
    {
        $submission->status = ResourceSubmissionStatus::InReview;
        $submission->save();

        $this->auditLog->record($actor, 'resource_submission.in_review', $submission, ['email' => $submission->email]);

        return $submission;
    }
}
