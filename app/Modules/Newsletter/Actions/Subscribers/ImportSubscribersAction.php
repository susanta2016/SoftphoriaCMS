<?php

namespace App\Modules\Newsletter\Actions\Subscribers;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Modules\Newsletter\Actions\EnsuresNewsletterAdmin;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Support\SuppressionList;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use SplFileObject;

/**
 * CSV import (columns: email, first_name, last_name — header row required,
 * any order, other columns ignored). Streamed row by row, never loaded
 * whole. The admin must attest that every row consented.
 *
 * Never weakens an existing subscriber's state: a new address becomes
 * Active; an existing Active one just gains the groups (and any missing
 * name); an Unsubscribed, Pending or suppressed one is left exactly as it
 * is — not resubscribed, not added to groups — and counted as skipped. An
 * address on the suppression list without a subscriber row is skipped too.
 */
class ImportSubscribersAction
{
    use EnsuresNewsletterAdmin;

    public const MAX_ROWS = 20000;

    public function __construct(
        private readonly SuppressionList $suppressions,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<int, int|string>  $groupIds
     * @return array{created: int, updated: int, skipped: int, invalid: int}
     */
    public function handle(User $admin, string $path, array $groupIds, bool $consentConfirmed, string $source = 'import'): array
    {
        $this->ensureAdmin($admin);

        if (! $consentConfirmed) {
            throw NewsletterException::invalid('Confirm that everyone in the file agreed to receive the newsletter.');
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        $header = null;
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'invalid' => 0];
        $groupIds = array_map('intval', $groupIds);
        $rows = 0;

        foreach ($file as $line) {
            if (! is_array($line) || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($cell): string => mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell))), $line);

                if (! in_array('email', $header, true)) {
                    throw NewsletterException::invalid('The CSV needs a header row with an "email" column.');
                }

                continue;
            }

            if (++$rows > self::MAX_ROWS) {
                throw NewsletterException::invalid('Import at most '.self::MAX_ROWS.' rows per file. Earlier rows were imported.');
            }

            $row = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), null));
            $outcome = $this->importRow($row, $groupIds, $source);
            $result[$outcome]++;
        }

        $this->auditLog->record($admin, 'newsletter_subscribers.imported', $admin, [...$result, 'group_ids' => $groupIds, 'source' => $source]);

        return $result;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<int, int>  $groupIds
     * @return 'created'|'updated'|'skipped'|'invalid'
     */
    private function importRow(array $row, array $groupIds, string $source): string
    {
        $email = NewsletterSubscriber::normalizeEmail((string) ($row['email'] ?? ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
            return 'invalid';
        }

        $first = self::clean($row['first_name'] ?? null);
        $last = self::clean($row['last_name'] ?? null);

        return DB::transaction(function () use ($email, $first, $last, $groupIds, $source): string {
            $subscriber = NewsletterSubscriber::query()->where('email', $email)->lockForUpdate()->first();

            if ($subscriber === null) {
                if ($this->suppressions->isSuppressed($email)) {
                    return 'skipped';
                }

                $subscriber = NewsletterSubscriber::query()->create([
                    'email' => $email,
                    'first_name' => $first,
                    'last_name' => $last,
                    'name' => trim("{$first} {$last}") ?: null,
                    'status' => SubscriberStatus::Subscribed->value,
                    'source' => $source,
                    'consented_at' => now(),
                ]);
                $subscriber->groups()->syncWithoutDetaching($groupIds);

                return 'created';
            }

            if ($subscriber->status !== SubscriberStatus::Subscribed->value || $this->suppressions->isSuppressed($email)) {
                return 'skipped';
            }

            $subscriber->forceFill([
                'first_name' => $subscriber->first_name ?: $first,
                'last_name' => $subscriber->last_name ?: $last,
            ])->save();
            $subscriber->groups()->syncWithoutDetaching($groupIds);

            return 'updated';
        });
    }

    private static function clean(?string $value): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, 100);
    }
}
