<?php

namespace App\Modules\InspirationalResources\Actions;

use App\Models\User;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Services\AuditLogService;

/**
 * Admin "Add Resource" (gated by
 * config('features.inspirational_resources_admin_create_enabled')) — an
 * admin adding a resource directly rather than it arriving through the
 * public form. Same resource_submissions row and review-queue statuses as
 * CreateResourceSubmissionAction, but deliberately sends no emails: there's
 * no outside submitter to acknowledge, and no admin to alert about their own
 * entry. Picking Approved here publishes it immediately without going
 * through ApproveResourceSubmissionAction, for the same reason — its
 * "published" email is only meant for a public-form submitter. The admin is
 * recorded as `user_id` (so the public avatar resolves to theirs) and the
 * creation is written to the audit log like every other status change.
 */
class CreateAdminResourceSubmissionAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): ResourceSubmission
    {
        $submission = new ResourceSubmission;
        $submission->fill($data);
        $submission->user_id = $actor->getKey();
        $submission->slug = ResourceSubmission::uniqueSlug($submission->subject ?: $submission->name);
        $submission->save();

        $this->auditLog->record($actor, 'resource_submission.created_by_admin', $submission, [
            'status' => $submission->status->value,
        ]);

        return $submission;
    }
}
