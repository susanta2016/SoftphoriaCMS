<?php

namespace App\Modules\PoetryProse\Actions;

use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseSubmissionStatus;
use App\Modules\PoetryProse\Models\PoetryProseSubmission;
use App\Shared\Services\AuditLogService;

/**
 * The admin review-queue transitions (Mark In Review / Approve / Archive).
 * Pure status bookkeeping: approving publishes nothing and sends no email.
 * The audit log is where "who reviewed this, and when" lives, same as
 * Inspirational Resources.
 */
class ChangePoetryProseSubmissionStatusAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(PoetryProseSubmission $submission, PoetryProseSubmissionStatus $status, User $actor): PoetryProseSubmission
    {
        $submission->status = $status;
        $submission->save();

        $this->auditLog->record($actor, 'poetry_prose_submission.'.$status->value, $submission, ['email' => $submission->email]);

        return $submission;
    }
}
