<?php

namespace App\Jobs\Notifications;

use App\Enums\EmailRecipientType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emails one new-content alert (see NewContentAlerter) to every Active
 * member — the same recipient definition SendGratitudeJournalRemindersCommand
 * uses. Runs on the existing Redis queue so publishing never waits on a
 * large mailing. Carries only the template key and plain-string tokens,
 * never the content model, so a deleted item can't fail the job.
 *
 * $tries = 1 (overrides the worker's --tries=3): a retry would re-send to
 * every member already reached before the failure. Each recipient is sent
 * in its own try/catch instead, so one bad address or SMTP hiccup only
 * skips that member.
 */
class SendNewContentAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /**
     * @param  array<string, string>  $variables
     */
    public function __construct(
        public readonly string $notificationKey,
        public readonly array $variables,
    ) {}

    public function handle(TemplatedMailer $mailer): void
    {
        // Disabled (or missing) template: skip the member loop entirely.
        if ($mailer->renderAsMailable($this->notificationKey, EmailRecipientType::User, $this->variables) === null) {
            return;
        }

        User::query()
            ->where('status', UserStatus::Active)
            ->chunkById(200, function ($users) use ($mailer): void {
                foreach ($users as $user) {
                    try {
                        $mailer->send($this->notificationKey, EmailRecipientType::User, $user->email, [
                            ...$this->variables,
                            'user_name' => $user->name,
                        ]);
                    } catch (Throwable $exception) {
                        Log::warning('New content member alert failed to send', [
                            'notification_key' => $this->notificationKey,
                            'user_id' => $user->id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }
}
