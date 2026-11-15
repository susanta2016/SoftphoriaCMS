<?php

namespace App\Actions\Contact;

use App\Models\ContactBan;
use App\Models\ContactRequest;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Collection;

/**
 * Bans the senders of the given contact requests by the chosen criteria
 * (any of ContactBan::TYPES). Already-banned values are skipped, and a
 * request with no recorded IP simply contributes no IP ban. Optionally
 * deletes the requests afterwards through DeleteContactRequestAction (soft
 * delete + audit entry, like the row Delete action).
 */
class BanContactSendersAction
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly DeleteContactRequestAction $deleteContactRequest,
    ) {}

    /**
     * @param  Collection<int, ContactRequest>  $contactRequests
     * @param  list<string>  $types
     * @return int the number of new bans created
     */
    public function handle(Collection $contactRequests, array $types, ?string $reason, User $actor, bool $deleteRequests = false): int
    {
        $types = array_values(array_intersect($types, array_keys(ContactBan::TYPES)));
        $created = 0;

        foreach ($contactRequests as $contactRequest) {
            foreach ($types as $type) {
                $raw = match ($type) {
                    ContactBan::TYPE_IP => $contactRequest->ip_address,
                    ContactBan::TYPE_EMAIL => $contactRequest->email,
                    ContactBan::TYPE_NAME => $contactRequest->name,
                };

                if (blank($raw)) {
                    continue;
                }

                $ban = ContactBan::query()->firstOrCreate(
                    ['type' => $type, 'value' => ContactBan::normalize($type, $raw)],
                    [
                        'reason' => filled($reason) ? mb_substr($reason, 0, 255) : null,
                        'source_submission_id' => $contactRequest->id,
                        'banned_by' => $actor->getKey(),
                    ],
                );

                if ($ban->wasRecentlyCreated) {
                    $created++;
                    $this->auditLog->record($actor, 'contact_ban.created', $ban, [
                        'type' => $type,
                        'value' => $ban->value,
                        'contact_request_id' => $contactRequest->id,
                    ]);
                }
            }
        }

        if ($deleteRequests) {
            foreach ($contactRequests as $contactRequest) {
                $this->deleteContactRequest->handle($contactRequest, $actor);
            }
        }

        return $created;
    }
}
