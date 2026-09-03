<?php

namespace App\Actions\Contact;

use App\Models\ContactRequest;
use App\Models\User;
use App\Shared\Services\AuditLogService;

/**
 * The only path by which an admin changes a contact request's workflow
 * fields (status/resolution notes) — the submission's own content
 * (name/email/phone/subject/category/message) is never admin-editable, per
 * the approved ADMIN-010 scope ("no create/edit UI for submissions").
 */
class UpdateContactRequestAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ContactRequest $contactRequest, array $data, User $actor): ContactRequest
    {
        // Set individually rather than fill() — status/resolution_notes are
        // deliberately excluded from ContactRequest's #[Fillable] surface
        // (they're admin-only workflow fields, never mass-assignable from
        // the public form), so fill() would silently drop them here too.
        $contactRequest->status = $data['status'];
        $contactRequest->resolution_notes = $data['resolution_notes'] ?? null;
        $changedAttributes = array_keys($contactRequest->getDirty());

        if ($changedAttributes !== []) {
            $contactRequest->updated_by = $actor->getKey();
        }

        $contactRequest->save();

        if ($changedAttributes !== []) {
            $this->auditLog->record($actor, 'contact_request.updated', $contactRequest, [
                'changed' => $changedAttributes,
            ]);
        }

        return $contactRequest;
    }
}
