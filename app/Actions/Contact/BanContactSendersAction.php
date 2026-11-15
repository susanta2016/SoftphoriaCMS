<?php

namespace App\Actions\Contact;

use App\Models\ContactBan;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Collection;

/**
 * Bans the senders of the given Contact Us submissions by the chosen
 * criteria (any of ContactBan::TYPES). Already-banned values are skipped,
 * and a submission with no recorded IP simply contributes no IP ban.
 * Optionally deletes the submissions afterwards.
 */
class BanContactSendersAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  Collection<int, ContactSubmission>  $submissions
     * @param  list<string>  $types
     * @return int the number of new bans created
     */
    public function handle(Collection $submissions, array $types, ?string $reason, ?User $actor, bool $deleteSubmissions = false): int
    {
        $types = array_values(array_intersect($types, array_keys(ContactBan::TYPES)));
        $created = 0;

        foreach ($submissions as $submission) {
            foreach ($types as $type) {
                $raw = match ($type) {
                    ContactBan::TYPE_IP => $submission->ip_address,
                    ContactBan::TYPE_EMAIL => $submission->email,
                    ContactBan::TYPE_NAME => $submission->name,
                };

                if (blank($raw)) {
                    continue;
                }

                $ban = ContactBan::query()->firstOrCreate(
                    ['type' => $type, 'value' => ContactBan::normalize($type, $raw)],
                    [
                        'reason' => filled($reason) ? mb_substr($reason, 0, 255) : null,
                        'source_submission_id' => $submission->id,
                        'banned_by' => $actor?->getKey(),
                    ],
                );

                if ($ban->wasRecentlyCreated) {
                    $created++;
                    $this->auditLog->record($actor, 'contact_ban.created', $ban, [
                        'type' => $type,
                        'value' => $ban->value,
                        'submission_id' => $submission->id,
                    ]);
                }
            }
        }

        if ($deleteSubmissions) {
            $submissions->each->delete();
        }

        return $created;
    }
}
