<?php

namespace Tests\Feature\GratitudeJournal;

use App\Enums\GratitudeJournalVisibility;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Services\Notifications\TemplatedMailer;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * "gratitude_journal_submitted" (App\Actions\GratitudeJournal\
 * CreateGratitudeJournalEntryAction) — sent on every save regardless of
 * visibility. Never quotes the entry's own content — only user_name,
 * visibility_label, and a link back to the journal are passed as variables.
 */
class GratitudeJournalAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_entry_sends_an_acknowledgement_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => 'Grateful for a quiet morning.',
            'visibility' => GratitudeJournalVisibility::Public->value,
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($user->email)
            && str_contains($mail->subjectLine, 'Gratitude Journal Entry Was Saved'));
    }

    public function test_a_private_entry_also_sends_an_acknowledgement_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => 'A private reflection.',
            'visibility' => GratitudeJournalVisibility::Private->value,
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($user->email)
            && str_contains($mail->subjectLine, 'Gratitude Journal Entry Was Saved'));
    }

    public function test_a_failed_acknowledgement_email_does_not_break_the_entry_save(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create();

        $mailer = Mockery::mock(TemplatedMailer::class);
        $mailer->shouldReceive('send')->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->app->instance(TemplatedMailer::class, $mailer);

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => 'Grateful even when email is down.',
            'visibility' => GratitudeJournalVisibility::Public->value,
        ]);

        $response->assertRedirect(route('account.gratitude-journal.index'));
        $this->assertSame(1, $user->lightPosts()->journal()->count());
    }
}
