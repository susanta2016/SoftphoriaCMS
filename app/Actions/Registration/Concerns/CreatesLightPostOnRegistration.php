<?php

namespace App\Actions\Registration\Concerns;

use App\Enums\EmailRecipientType;
use App\Enums\GratitudeJournalVisibility;
use App\Enums\LightPostSource;
use App\Models\User;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared by Free/Pro registration — creates the visitor's first Light Post
 * (the "Leave a Little Light ✨" registration prompt) only when they
 * explicitly chose "Share My Light" (light_post_action === 'share') and
 * actually wrote something. "Share Another Time" (or a blank message even
 * with 'share' selected) creates nothing — an empty Light Post is never
 * created either way. Always public, per the prompt's own copy telling the
 * visitor up front the post will be shared publicly. Always
 * source = registration — this trait is never used by the Gratitude
 * Journal (App\Actions\GratitudeJournal), which sets source = journal
 * instead (Gratitude Journal audit §3).
 *
 * The "light_post_submitted" acknowledgement lives here rather than in each
 * caller's own try/catch (alongside user_registered/email_verification in
 * RegisterFreeUserAction) so the create-then-acknowledge pair stays one
 * cohesive unit no matter which registration path calls it, and so this
 * email is never sent when no Light Post was actually created.
 */
trait CreatesLightPostOnRegistration
{
    /**
     * @param  array{light_post_action?: ?string, light_message?: ?string}  $data
     */
    protected function createLightPostIfRequested(User $user, array $data, TemplatedMailer $mailer): void
    {
        if (($data['light_post_action'] ?? null) !== 'share') {
            return;
        }

        $content = trim((string) ($data['light_message'] ?? ''));

        if ($content === '') {
            return;
        }

        $lightPost = $user->lightPosts()->create([
            'source' => LightPostSource::Registration,
            'content' => $content,
            'visibility' => GratitudeJournalVisibility::Public,
        ]);

        // A broken SMTP config must never turn a successful signup into a
        // 500 for the visitor — the Light Post is already saved above.
        try {
            $mailer->send('light_post_submitted', EmailRecipientType::User, $user->email, [
                'user_name' => $user->name,
                'light_post_url' => route('light-posts.show', $lightPost),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Light Post submission acknowledgement email failed to send', [
                'user_id' => $user->getKey(),
                'light_post_id' => $lightPost->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
