<?php

namespace App\Modules\InspirationalResources\Actions;

use App\Models\User;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Services\AuditLogService;

class ArchiveResourceSubmissionAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(ResourceSubmission $submission, User $actor): ResourceSubmission
    {
        $submission->status = ResourceSubmissionStatus::Archived;
        $submission->save();

        $this->auditLog->record($actor, 'resource_submission.archived', $submission, ['email' => $submission->email]);

        return $submission;
    }
}
