<?php

namespace App\Shared\Services\Notifications;

use App\Jobs\Notifications\SendNewContentAlertJob;
use App\Shared\Support\Notifications\AnnouncesNewContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "New content" alerts to every Active member — distinct from the
 * transactional emails sent to one submitter/member. Duplicate protection
 * is the unique (alertable_type, alertable_id) index on new_content_alerts:
 * whichever save inserts the row first owns the alert, every later save
 * (edits, re-publishing after an archive, a second hook firing in the same
 * request, a concurrent request) inserts nothing and returns. The claim is
 * made even when the template is disabled, so re-enabling it later never
 * back-fills alerts for content published while it was off.
 */
class NewContentAlerter
{
    public function announce(Model&AnnouncesNewContent $content): void
    {
        $claimed = DB::table('new_content_alerts')->insertOrIgnore([
            'alertable_type' => $content->getMorphClass(),
            'alertable_id' => $content->getKey(),
            'notification_key' => $content->newContentAlertKey(),
            'created_at' => now(),
        ]);

        if ($claimed === 0) {
            return;
        }

        $notificationKey = $content->newContentAlertKey();

        // Building the alert's tokens (e.g. route() on a missing/empty
        // slug → UrlGenerationException) is notification preparation, so
        // it gets the same non-blocking treatment as sending: logged, never
        // thrown back into the save that published the content. The claim
        // row stays, so a later edit never sends a late alert.
        try {
            $variables = $content->newContentAlertVariables();
        } catch (Throwable $exception) {
            Log::warning('New content member alert could not be prepared', [
                'notification_key' => $notificationKey,
                'alertable_type' => $content->getMorphClass(),
                'alertable_id' => $content->getKey(),
                'exception' => $exception::class.': '.$exception->getMessage(),
            ]);

            return;
        }

        // After commit: the publishing Actions wrap their save in a
        // transaction, and a rolled-back publish must not email anyone
        // (its claim row rolls back with it). A queue outage must never
        // turn a successful publish into an error for the admin.
        DB::afterCommit(function () use ($content, $notificationKey, $variables): void {
            try {
                SendNewContentAlertJob::dispatch($notificationKey, $variables);
            } catch (Throwable $exception) {
                Log::warning('New content member alert failed to dispatch', [
                    'notification_key' => $notificationKey,
                    'alertable_type' => $content->getMorphClass(),
                    'alertable_id' => $content->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }
}
