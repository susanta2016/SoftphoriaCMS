<?php

namespace Tests\Feature\Admin;

use App\Actions\Contact\SendContactSubmissionReplyAction;
use App\Enums\ContactSubmissionReplyStatus;
use App\Enums\UserStatus;
use App\Exceptions\Contact\ContactSubmissionReplyException;
use App\Filament\Resources\ContactSubmissions\Pages\ListContactSubmissions;
use App\Filament\Resources\ContactSubmissions\Pages\ViewContactSubmission;
use App\Models\AuditLog;
use App\Models\ContactSubmission;
use App\Models\ContactSubmissionReply;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Admin > Contact > Submissions "Reply"
 * (config('features.contact_submission_reply_enabled')): the flag, the
 * server-side checks in SendContactSubmissionReplyAction, the email, the
 * reply history and the duplicate/failure handling.
 */
class ContactSubmissionReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_the_feature_is_disabled_by_default(): void
    {
        $backup = [$_ENV['CONTACT_SUBMISSION_REPLY_ENABLED'] ?? null, $_SERVER['CONTACT_SUBMISSION_REPLY_ENABLED'] ?? null, getenv('CONTACT_SUBMISSION_REPLY_ENABLED')];
        unset($_ENV['CONTACT_SUBMISSION_REPLY_ENABLED'], $_SERVER['CONTACT_SUBMISSION_REPLY_ENABLED']);
        putenv('CONTACT_SUBMISSION_REPLY_ENABLED');

        try {
            $this->assertFalse((bool) (require config_path('features.php'))['contact_submission_reply_enabled']);
        } finally {
            [$env, $server, $getenv] = $backup;
            if ($env !== null) {
                $_ENV['CONTACT_SUBMISSION_REPLY_ENABLED'] = $env;
            }
            if ($server !== null) {
                $_SERVER['CONTACT_SUBMISSION_REPLY_ENABLED'] = $server;
            }
            if ($getenv !== false) {
                putenv("CONTACT_SUBMISSION_REPLY_ENABLED={$getenv}");
            }
        }

        $this->assertFalse(SendContactSubmissionReplyAction::isEnabled(), 'Tests run with the flag off unless a test enables it.');
    }

    public function test_when_disabled_the_reply_action_is_hidden(): void
    {
        $submission = $this->submission();

        Livewire::actingAs($this->admin())
            ->test(ListContactSubmissions::class)
            ->assertTableActionHidden('reply', $submission);

        Livewire::actingAs($this->admin())
            ->test(ViewContactSubmission::class, ['record' => $submission->getRouteKey()])
            ->assertActionHidden('reply');
    }

    public function test_when_disabled_a_submitted_reply_is_rejected_server_side(): void
    {
        Mail::fake();
        $submission = $this->submission();

        // Hand-crafted Livewire calls for the hidden action do nothing…
        Livewire::actingAs($this->admin())
            ->test(ViewContactSubmission::class, ['record' => $submission->getRouteKey()])
            ->call('mountAction', 'reply')
            ->set('mountedActions.0.data', ['subject' => 'Hi', 'message' => 'Hello', 'idempotency_key' => (string) Str::uuid()])
            ->call('callMountedAction');

        // …and the action class refuses on its own.
        try {
            $this->sendAction()->handle($submission, $this->admin(), 'Hi', 'Hello', (string) Str::uuid());
            $this->fail('Expected the reply to be refused while the feature is off.');
        } catch (ContactSubmissionReplyException $exception) {
            $this->assertSame(ContactSubmissionReplyException::disabled()->getMessage(), $exception->getMessage());
        }

        Mail::assertNothingSent();
        $this->assertDatabaseCount('contact_submission_replies', 0);
    }

    public function test_when_enabled_the_reply_action_is_visible(): void
    {
        $this->enable();
        $submission = $this->submission();

        Livewire::actingAs($this->admin())
            ->test(ListContactSubmissions::class)
            ->assertTableActionVisible('reply', $submission);
    }

    public function test_the_reply_form_defaults_to_the_fallback_subject(): void
    {
        $this->enable();
        $submission = $this->submission();

        Livewire::actingAs($this->admin())
            ->test(ViewContactSubmission::class, ['record' => $submission->getRouteKey()])
            ->mountAction('reply')
            ->assertActionDataSet(['subject' => 'Re: Your message to '.config('app.name')]);
    }

    public function test_admin_can_send_a_reply_to_the_stored_address(): void
    {
        $this->enable();
        Mail::fake();
        $admin = $this->admin();
        $submission = $this->submission(['email' => 'jane@example.com', 'message' => 'Do you take commissions?']);

        Livewire::actingAs($admin)
            ->test(ListContactSubmissions::class)
            ->callTableAction('reply', $submission, data: [
                'subject' => 'About your commission question',
                'message' => "Yes we do.\n\nLet's talk.",
                // Not a form field: an attempt to redirect the reply is ignored.
                'email' => 'attacker@example.com',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Reply sent to jane@example.com.');

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail): bool {
            $mail->build();

            return $mail->hasTo('jane@example.com')
                && ! $mail->hasTo('attacker@example.com')
                && count($mail->to) === 1
                && $mail->cc === [] && $mail->bcc === []
                && $mail->subjectLine === 'About your commission question'
                && str_contains($mail->render(), 'Yes we do.')
                && str_contains($mail->render(), 'Do you take commissions?');
        });

        $reply = ContactSubmissionReply::query()->sole();
        $this->assertSame($submission->id, $reply->contact_submission_id);
        $this->assertSame($admin->id, $reply->sent_by_user_id);
        $this->assertSame('jane@example.com', $reply->recipient_email);
        $this->assertSame('About your commission question', $reply->subject);
        $this->assertSame("Yes we do.\n\nLet's talk.", $reply->message);
        $this->assertSame(ContactSubmissionReplyStatus::Sent, $reply->status);
        $this->assertNotNull($reply->sent_at);

        $this->assertTrue(AuditLog::query()->where('action', 'contact_submission.reply_sent')->where('entity_id', $submission->id)->exists());

        // The original submission is untouched.
        $this->assertSame('Do you take commissions?', $submission->fresh()->message);
    }

    public function test_user_content_is_escaped_in_the_html_and_plain_in_the_subject_and_text(): void
    {
        $this->enable();
        Mail::fake();
        $submission = $this->submission(['name' => '<b>Jane</b>', 'message' => "<script>alert(1)</script>\nLine two"]);

        $this->sendAction()->handle($submission, $this->admin(), 'Fish & <Chips>', '<img src=x onerror=alert(1)> & thanks', (string) Str::uuid());

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail): bool {
            $mail->build();
            $html = $mail->render();

            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringNotContainsString('<img src=x', $html);
            $this->assertStringNotContainsString('<b>Jane</b>', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;<br>Line two', $html);
            $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt; &amp; thanks', $html);
            $this->assertSame('Fish & <Chips>', $mail->subjectLine);
            $this->assertStringContainsString('<img src=x onerror=alert(1)> & thanks', (string) $mail->textView);

            return true;
        });
    }

    public function test_reply_history_is_displayed_on_the_view_page(): void
    {
        $this->enable();
        Mail::fake();
        $admin = $this->admin(['name' => 'Ada Admin']);
        $submission = $this->submission();

        $this->sendAction()->handle($submission, $admin, 'First subject', 'First reply body', (string) Str::uuid());

        Livewire::actingAs($admin)
            ->test(ViewContactSubmission::class, ['record' => $submission->getRouteKey()])
            ->assertSee('Replies')
            ->assertSee('Ada Admin')
            ->assertSee('First subject')
            ->assertSee('First reply body')
            ->assertSee('Sent');
    }

    public function test_reply_history_stays_visible_after_the_feature_is_turned_off(): void
    {
        $this->enable();
        Mail::fake();
        $submission = $this->submission();
        $this->sendAction()->handle($submission, $this->admin(), 'Kept subject', 'Kept body', (string) Str::uuid());

        config(['features.contact_submission_reply_enabled' => false]);

        Livewire::actingAs($this->admin())
            ->test(ViewContactSubmission::class, ['record' => $submission->getRouteKey()])
            ->assertSee('Kept subject')
            ->assertActionHidden('reply');
    }

    public function test_multiple_replies_are_all_preserved(): void
    {
        $this->enable();
        Mail::fake();
        $submission = $this->submission();
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ViewContactSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('reply', data: ['subject' => 'Reply one', 'message' => 'Body one'])
            ->assertHasNoActionErrors()
            ->callAction('reply', data: ['subject' => 'Reply two', 'message' => 'Body two'])
            ->assertHasNoActionErrors()
            ->assertSee('Reply one')
            ->assertSee('Reply two');

        Mail::assertSentCount(2);
        $this->assertSame(['Reply one', 'Reply two'], $submission->replies()->reorder('id')->pluck('subject')->all());
    }

    public function test_non_admin_cannot_send_a_reply(): void
    {
        $this->enable();
        Mail::fake();
        $member = User::factory()->create(['status' => UserStatus::Active->value]);
        $submission = $this->submission();

        $this->actingAs($member)->get('/admin/contact-submissions/'.$submission->getRouteKey())->assertForbidden();

        $this->expectExceptionObject(ContactSubmissionReplyException::notAllowed());

        try {
            $this->sendAction()->handle($submission, $member, 'Hi', 'Hello', (string) Str::uuid());
        } finally {
            Mail::assertNothingSent();
            $this->assertDatabaseCount('contact_submission_replies', 0);
        }
    }

    public function test_a_suspended_admin_cannot_send_a_reply(): void
    {
        $this->enable();
        Mail::fake();

        $this->expectExceptionObject(ContactSubmissionReplyException::notAllowed());

        $this->sendAction()->handle($this->submission(), $this->admin(['status' => UserStatus::Suspended->value]), 'Hi', 'Hello', (string) Str::uuid());
    }

    public function test_a_missing_submission_cannot_be_replied_to(): void
    {
        $this->enable();

        $this->actingAs($this->admin())->get('/admin/contact-submissions/999999')->assertNotFound();
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function invalidInput(): array
    {
        return [
            'empty subject' => [['subject' => '', 'message' => 'Hello'], 'subject'],
            'empty message' => [['subject' => 'Hi', 'message' => ''], 'message'],
            'whitespace-only message' => [['subject' => 'Hi', 'message' => "   \n  "], 'message'],
            'oversized subject' => [['subject' => str_repeat('s', SendContactSubmissionReplyAction::SUBJECT_MAX_LENGTH + 1), 'message' => 'Hello'], 'subject'],
            'oversized message' => [['subject' => 'Hi', 'message' => str_repeat('m', SendContactSubmissionReplyAction::MESSAGE_MAX_LENGTH + 1)], 'message'],
        ];
    }

    /**
     * @param  array<string, string>  $data
     */
    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected_in_the_form(array $data, string $field): void
    {
        $this->enable();
        Mail::fake();
        $submission = $this->submission();

        Livewire::actingAs($this->admin())
            ->test(ViewContactSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('reply', data: $data)
            ->assertHasActionErrors([$field]);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('contact_submission_replies', 0);
    }

    /**
     * @param  array<string, string>  $data
     */
    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected_by_the_action_itself(array $data, string $field): void
    {
        $this->enable();
        Mail::fake();

        try {
            $this->sendAction()->handle($this->submission(), $this->admin(), $data['subject'], $data['message'], (string) Str::uuid());
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }

        Mail::assertNothingSent();
        $this->assertDatabaseCount('contact_submission_replies', 0);
    }

    public function test_a_subject_with_a_line_break_is_rejected(): void
    {
        $this->enable();

        $this->expectException(ValidationException::class);

        $this->sendAction()->handle($this->submission(), $this->admin(), "Hi\r\nBcc: x@example.com", 'Hello', (string) Str::uuid());
    }

    public function test_mail_failure_marks_the_reply_failed_keeps_the_form_open_and_hides_the_error(): void
    {
        $this->enable();
        Mail::shouldReceive('to')->andThrow(new TransportException('Connection to smtp.internal-host:587 failed, user secret-user'));
        $submission = $this->submission();

        Livewire::actingAs($this->admin())
            ->test(ViewContactSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('reply', data: ['subject' => 'Hi', 'message' => 'Hello there'])
            ->assertNotified(ContactSubmissionReplyException::sendFailed()->getMessage())
            ->assertActionMounted('reply')
            ->assertActionDataSet(['subject' => 'Hi', 'message' => 'Hello there'])
            ->assertDontSee('smtp.internal-host')
            ->assertDontSee('secret-user');

        $reply = ContactSubmissionReply::query()->sole();
        $this->assertSame(ContactSubmissionReplyStatus::Failed, $reply->status);
        $this->assertNull($reply->sent_at);
        $this->assertNotNull($reply->failed_at);
        $this->assertTrue(AuditLog::query()->where('action', 'contact_submission.reply_failed')->exists());
    }

    public function test_retrying_a_failed_reply_reuses_its_record(): void
    {
        $this->enable();
        $submission = $this->submission();
        $admin = $this->admin();
        $key = (string) Str::uuid();
        Mail::fake();

        // First attempt fails (template off), the admin fixes it and retries
        // from the same still-open form, i.e. with the same key.
        EmailTemplate::query()->where('notification_key', 'contact_submission_reply')->update(['is_enabled' => false]);

        try {
            $this->sendAction()->handle($submission, $admin, 'Hi', 'First draft', $key);
            $this->fail('Expected the send to fail.');
        } catch (ContactSubmissionReplyException) {
        }

        EmailTemplate::query()->where('notification_key', 'contact_submission_reply')->update(['is_enabled' => true]);

        $reply = $this->sendAction()->handle($submission, $admin, 'Hi', 'Edited draft', $key);

        Mail::assertSentCount(1);
        $this->assertDatabaseCount('contact_submission_replies', 1);
        $this->assertSame(ContactSubmissionReplyStatus::Sent, $reply->status);
        $this->assertSame('Edited draft', $reply->fresh()->message);
        $this->assertNull($reply->fresh()->failed_at);
    }

    public function test_a_disabled_template_fails_instead_of_pretending_to_send(): void
    {
        $this->enable();
        Mail::fake();
        EmailTemplate::query()->where('notification_key', 'contact_submission_reply')->update(['is_enabled' => false]);

        try {
            $this->sendAction()->handle($this->submission(), $this->admin(), 'Hi', 'Hello', (string) Str::uuid());
            $this->fail('Expected the reply to fail.');
        } catch (ContactSubmissionReplyException $exception) {
            $this->assertSame(ContactSubmissionReplyException::templateUnavailable()->getMessage(), $exception->getMessage());
        }

        Mail::assertNothingSent();
        $this->assertSame(ContactSubmissionReplyStatus::Failed, ContactSubmissionReply::query()->sole()->status);
    }

    public function test_a_double_submit_with_the_same_key_sends_once_and_stores_once(): void
    {
        $this->enable();
        Mail::fake();
        $submission = $this->submission();
        $admin = $this->admin();
        $key = (string) Str::uuid();

        $first = $this->sendAction()->handle($submission, $admin, 'Hi', 'Hello', $key);
        $second = $this->sendAction()->handle($submission, $admin, 'Hi', 'Hello', $key);

        $this->assertTrue($first->is($second));
        Mail::assertSentCount(1);
        $this->assertDatabaseCount('contact_submission_replies', 1);
    }

    public function test_a_key_already_in_flight_is_not_sent_again(): void
    {
        $this->enable();
        Mail::fake();
        $submission = $this->submission();
        $key = (string) Str::uuid();

        ContactSubmissionReply::query()->create([
            'contact_submission_id' => $submission->id,
            'recipient_email' => $submission->email,
            'subject' => 'Hi',
            'message' => 'Hello',
            'status' => ContactSubmissionReplyStatus::Pending,
            'idempotency_key' => $key,
        ]);

        $reply = $this->sendAction()->handle($submission, $this->admin(), 'Hi', 'Hello', $key);

        $this->assertSame(ContactSubmissionReplyStatus::Pending, $reply->status);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('contact_submission_replies', 1);
    }

    public function test_a_key_from_another_submission_is_refused(): void
    {
        $this->enable();
        Mail::fake();
        $admin = $this->admin();
        $key = (string) Str::uuid();

        $this->sendAction()->handle($this->submission(), $admin, 'Hi', 'Hello', $key);

        $this->expectExceptionObject(ContactSubmissionReplyException::keyMismatch());

        $this->sendAction()->handle($this->submission(['email' => 'other@example.com']), $admin, 'Hi', 'Hello', $key);
    }

    private function enable(): void
    {
        config(['features.contact_submission_reply_enabled' => true]);
    }

    private function sendAction(): SendContactSubmissionReplyAction
    {
        return app(SendContactSubmissionReplyAction::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create(['status' => UserStatus::Active->value, ...$attributes]);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submission(array $overrides = []): ContactSubmission
    {
        return ContactSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-0100',
            'message' => 'A message worth reading.',
            ...$overrides,
        ]);
    }
}
