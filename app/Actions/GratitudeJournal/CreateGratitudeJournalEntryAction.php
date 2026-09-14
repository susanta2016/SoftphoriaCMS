<?php

namespace App\Actions\GratitudeJournal;

use App\Enums\EmailRecipientType;
use App\Enums\GratitudeJournalVisibility;
use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\User;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates a new Gratitude Journal entry for the authenticated member — a
 * light_posts row with source = journal (Gratitude Journal audit §3,
 * distinguishing it from CreatesLightPostOnRegistration's registration
 * posts, which this class never touches). Defaults to Public per the
 * client's confirmed default; a caller may pass Private or Community to
 * create an entry with either of those visibility states outright.
 *
 * Sends the "gratitude_journal_submitted" acknowledgement on every save
 * regardless of visibility — Public and Private both get one, since it
 * confirms receipt, not that the entry is now visible to anyone else.
 */
class CreateGratitudeJournalEntryAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    public function handle(User $user, string $content, GratitudeJournalVisibility $visibility = GratitudeJournalVisibility::Public): LightPost
    {
        $entry = $user->lightPosts()->create([
            'source' => LightPostSource::Journal,
            'content' => $content,
            'visibility' => $visibility,
        ]);

        // A broken SMTP config must never turn a successful save into a 500
        // for the member — the entry is already saved above.
        try {
            $this->mailer->send('gratitude_journal_submitted', EmailRecipientType::User, $user->email, [
                'user_name' => $user->name,
                'visibility_label' => $visibility->getLabel(),
                'journal_url' => route('account.gratitude-journal.index'),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Gratitude Journal submission acknowledgement email failed to send', [
                'user_id' => $user->getKey(),
                'entry_id' => $entry->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }

        return $entry;
    }
}
