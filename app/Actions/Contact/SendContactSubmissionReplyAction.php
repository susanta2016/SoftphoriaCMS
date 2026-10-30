<?php

namespace App\Actions\Contact;

use App\Enums\ContactSubmissionReplyStatus;
use App\Enums\EmailRecipientType;
use App\Exceptions\Contact\ContactSubmissionReplyException;
use App\Models\ContactSubmission;
use App\Models\ContactSubmissionReply;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use App\Shared\Services\Notifications\TemplatedMailer;
use App\Shared\Services\Settings\SettingsRepository;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Admin "Reply" on a Contact Us submission
 * (config('features.contact_submission_reply_enabled')). Every check is
 * made here on every send — the flag, the acting admin, the input — so
 * hiding the Filament action is never the protection. The recipient is
 * always the submission's stored email; the caller cannot supply one.
 *
 * Sent through the `contact_submission_reply` Email Template, synchronously
 * (the project's convention — TemplatedMailer::send() is never queued).
 * renderAsMailable() is used rather than send() because send() silently
 * does nothing when the template is disabled, which would look like a
 * successful reply. A reply row is written as Pending first, then marked
 * Sent only after the mail server accepted it, or Failed (keeping the
 * subject/message so the admin can retry).
 *
 * $idempotencyKey is one UUID per opened reply form: a double-click or a
 * retry with the same key never creates a second row, and never re-sends
 * a reply already Sent (or still being sent). Retrying a Failed reply
 * re-uses its row.
 */
class SendContactSubmissionReplyAction
{
    public const SUBJECT_MAX_LENGTH = 200;

    public const MESSAGE_MAX_LENGTH = 5000;

    public function __construct(
        private readonly TemplatedMailer $mailer,
        private readonly AuditLogService $auditLog,
        private readonly SettingsRepository $settings,
    ) {}

    public static function isEnabled(): bool
    {
        return (bool) config('features.contact_submission_reply_enabled');
    }

    /**
     * Submissions have no subject of their own, so this is always the
     * site-name fallback.
     */
    public function defaultSubject(): string
    {
        return 'Re: Your message to '.$this->settings->get('general', 'site_name', config('app.name'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:'.self::SUBJECT_MAX_LENGTH, 'not_regex:/[\r\n]/'],
            'message' => ['required', 'string', 'max:'.self::MESSAGE_MAX_LENGTH],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * Returns the reply row: Sent, or — when the same key was already used
     * — that earlier row as it stands (Sent or still Pending).
     *
     * @throws ContactSubmissionReplyException
     * @throws ValidationException
     */
    public function handle(ContactSubmission $submission, User $admin, string $subject, string $message, string $idempotencyKey): ContactSubmissionReply
    {
        if (! self::isEnabled()) {
            throw ContactSubmissionReplyException::disabled();
        }

        if (! $admin->canAccessPanel(Filament::getPanel('admin'))) {
            throw ContactSubmissionReplyException::notAllowed();
        }

        $data = Validator::make(
            ['subject' => trim($subject), 'message' => trim($message), 'idempotency_key' => $idempotencyKey],
            self::rules(),
        )->validate();

        [$reply, $shouldSend] = $this->claim($submission, $admin, $data);

        if (! $shouldSend) {
            return $reply;
        }

        try {
            $mailable = $this->mailer->renderAsMailable(
                'contact_submission_reply',
                EmailRecipientType::User,
                $this->variables($submission, $reply, escape: true),
                $this->variables($submission, $reply, escape: false),
            );

            if ($mailable === null) {
                $this->markFailed($reply, $admin, 'template_unavailable');

                throw ContactSubmissionReplyException::templateUnavailable();
            }

            Mail::to($reply->recipient_email)->send($mailable);
        } catch (ContactSubmissionReplyException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Contact submission reply failed to send', [
                'submission_id' => $submission->id,
                'reply_id' => $reply->id,
                'admin_id' => $admin->id,
                'exception' => $exception->getMessage(),
            ]);

            $this->markFailed($reply, $admin, 'send_failed');

            throw ContactSubmissionReplyException::sendFailed();
        }

        $reply->update([
            'status' => ContactSubmissionReplyStatus::Sent,
            'sent_at' => now(),
            'failed_at' => null,
        ]);

        $this->auditLog->record($admin, 'contact_submission.reply_sent', $submission, ['reply_id' => $reply->id]);

        return $reply;
    }

    /**
     * Creates the Pending row for a new key, or re-claims a Failed row for a
     * retry. Any other existing row (Sent, or Pending in another request) is
     * returned with $shouldSend = false. The unique index settles a race
     * between two first attempts with the same key.
     *
     * @param  array{subject: string, message: string, idempotency_key: string}  $data
     * @return array{0: ContactSubmissionReply, 1: bool}
     */
    private function claim(ContactSubmission $submission, User $admin, array $data): array
    {
        try {
            return DB::transaction(function () use ($submission, $admin, $data): array {
                $existing = ContactSubmissionReply::query()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ((int) $existing->contact_submission_id !== (int) $submission->id) {
                        throw ContactSubmissionReplyException::keyMismatch();
                    }

                    if ($existing->status !== ContactSubmissionReplyStatus::Failed) {
                        return [$existing, false];
                    }

                    $existing->update([
                        'sent_by_user_id' => $admin->id,
                        'recipient_email' => $submission->email,
                        'subject' => $data['subject'],
                        'message' => $data['message'],
                        'status' => ContactSubmissionReplyStatus::Pending,
                    ]);

                    return [$existing, true];
                }

                $reply = ContactSubmissionReply::query()->create([
                    'contact_submission_id' => $submission->id,
                    'sent_by_user_id' => $admin->id,
                    'recipient_email' => $submission->email,
                    'subject' => $data['subject'],
                    'message' => $data['message'],
                    'status' => ContactSubmissionReplyStatus::Pending,
                    'idempotency_key' => $data['idempotency_key'],
                ]);

                return [$reply, true];
            });
        } catch (UniqueConstraintViolationException) {
            $existing = ContactSubmissionReply::query()->where('idempotency_key', $data['idempotency_key'])->firstOrFail();

            if ((int) $existing->contact_submission_id !== (int) $submission->id) {
                throw ContactSubmissionReplyException::keyMismatch();
            }

            return [$existing, false];
        }
    }

    private function markFailed(ContactSubmissionReply $reply, User $admin, string $reason): void
    {
        $reply->update([
            'status' => ContactSubmissionReplyStatus::Failed,
            'failed_at' => now(),
        ]);

        $this->auditLog->record($admin, 'contact_submission.reply_failed', $reply->contactSubmission, [
            'reply_id' => $reply->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Escaped for the HTML body; raw for the subject and plain-text body
     * (see TemplatedMailer::renderAsMailable()). The original message is
     * flattened to one line of <br>s in HTML so it stays inside its single
     * <blockquote> paragraph (TemplatedMailer::formatHtmlBody() splits on
     * blank lines).
     *
     * @return array<string, string>
     */
    private function variables(ContactSubmission $submission, ContactSubmissionReply $reply, bool $escape): array
    {
        $originalMessage = $escape
            ? str_replace(["\r\n", "\r", "\n"], '<br>', e($submission->message))
            : $submission->message;

        return [
            'name' => $escape ? e($submission->name) : $submission->name,
            'reply_subject' => $escape ? e($reply->subject) : $reply->subject,
            'reply_message' => $escape ? e(str_replace(["\r\n", "\r"], "\n", $reply->message)) : $reply->message,
            'original_date' => $submission->created_at->format('F j, Y'),
            'original_message' => $originalMessage,
        ];
    }
}
