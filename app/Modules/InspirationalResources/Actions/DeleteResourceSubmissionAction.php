<?php

namespace App\Modules\InspirationalResources\Actions;

use App\Models\User;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Permanently removes a resource (client request 2026-09-26) — unlike
 * Archive, which only hides it. Nothing references resource_submissions by
 * foreign key; its SEO metadata row is removed with it, and the audit log
 * keeps who deleted what.
 */
class DeleteResourceSubmissionAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(ResourceSubmission $submission, User $actor): void
    {
        DB::transaction(function () use ($submission, $actor): void {
            $this->auditLog->record($actor, 'resource_submission.deleted', $submission, [
                'name' => $submission->name,
                'email' => $submission->email,
                'subject' => $submission->subject,
                'status' => $submission->status->value,
            ]);

            $submission->seo()->delete();
            $submission->delete();
        });
    }
}
