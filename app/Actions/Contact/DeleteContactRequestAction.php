<?php

namespace App\Actions\Contact;

use App\Models\ContactRequest;
use App\Models\User;
use App\Shared\Services\AuditLogService;

/**
 * Soft-deletes a contact request (ARCHITECTURE.md §13 — no hard deletes on
 * a table with a status column). Nothing else in the schema references
 * contact_requests rows, so unlike DeleteRoleAction/DeletePageAction this
 * needs no "still in use" guard — just the audit trail.
 */
class DeleteContactRequestAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(ContactRequest $contactRequest, User $actor): void
    {
        $name = $contactRequest->name;

        $contactRequest->delete();

        $this->auditLog->record($actor, 'contact_request.deleted', $contactRequest, ['name' => $name]);
    }
}
