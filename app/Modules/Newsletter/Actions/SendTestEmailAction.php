<?php

namespace App\Modules\Newsletter\Actions;

use App\Models\User;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Mail\NewsletterCampaignMail;
use App\Modules\Newsletter\Mail\NewsletterMailer;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use App\Modules\Newsletter\Support\NewsletterRenderer;
use App\Modules\Newsletter\Support\NewsletterSettings;
use App\Modules\Newsletter\Support\SenderIdentity;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Sends a "[TEST]" copy of a campaign (or a bare template) to up to
 * MAX_RECIPIENTS addresses the admin types — never to any subscriber list,
 * and no campaign_recipients row is written. Rendered exactly like a real
 * send, except untracked and with an inert unsubscribe link. Rate limited
 * per admin.
 */
class SendTestEmailAction
{
    use EnsuresNewsletterAdmin;

    public const MAX_RECIPIENTS = 5;

    public function __construct(
        private readonly NewsletterRenderer $renderer,
        private readonly NewsletterMailer $mailer,
        private readonly NewsletterSettings $settings,
        private readonly SenderIdentity $sender,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<int, string>  $recipients
     * @param  array{first_name?: ?string, last_name?: ?string, email?: ?string}|null  $personalization
     */
    public function handle(NewsletterCampaign|NewsletterTemplate $subject, User $admin, array $recipients, ?array $personalization = null): int
    {
        $this->ensureAdmin($admin);

        $recipients = array_values(array_unique(array_filter(array_map(fn ($email): string => mb_strtolower(trim((string) $email)), $recipients))));

        if ($recipients === [] || count($recipients) > self::MAX_RECIPIENTS) {
            throw NewsletterException::invalid('Enter between 1 and '.self::MAX_RECIPIENTS.' test addresses.');
        }

        foreach ($recipients as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw NewsletterException::invalid("\"{$email}\" is not a valid email address.");
            }
        }

        $key = 'newsletter-test-email:'.$admin->id;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw NewsletterException::invalid('Too many test emails — try again in a minute.');
        }

        RateLimiter::hit($key, 60);

        [$campaign, $template] = $subject instanceof NewsletterCampaign
            ? [$subject, $subject->template]
            : [self::sampleCampaign($subject, $this->settings), $subject];

        if ($template === null) {
            throw NewsletterException::invalid('Choose an email template first.');
        }

        if (($senderProblem = $this->sender->validate((string) $campaign->from_email)) !== null) {
            throw NewsletterException::invalid($senderProblem);
        }

        $personalization ??= NewsletterRenderer::sampleSubscriber();
        $email = $this->renderer->preview($campaign, $template, $personalization);
        $subjectLine = '[TEST] '.$this->renderer->subject($campaign, $personalization);

        try {
            foreach ($recipients as $to) {
                $this->mailer->send($to, new NewsletterCampaignMail($subjectLine, $email->html, $email->text, $campaign->from_email, $campaign->from_name));
            }
        } catch (Throwable $exception) {
            Log::warning('Newsletter test email failed to send', ['admin_id' => $admin->id, 'exception' => $exception->getMessage()]);

            throw NewsletterException::invalid('The test email could not be sent — check the newsletter mail settings.');
        }

        $this->auditLog->record($admin, 'newsletter.test_email_sent', $subject, ['recipient_count' => count($recipients)]);

        return count($recipients);
    }

    /**
     * An unsaved campaign carrying a template's own subject/preheader and
     * placeholder content — for previewing and testing a template alone.
     */
    public static function sampleCampaign(NewsletterTemplate $template, NewsletterSettings $settings): NewsletterCampaign
    {
        return new NewsletterCampaign([
            'name' => $template->name,
            'subject' => $template->subject ?: $template->name,
            'preheader' => $template->preheader,
            'from_name' => $settings->fromName(),
            'from_email' => $settings->fromEmail(),
            'content' => '<p>This is where each campaign\'s own content appears. Write it when you create a campaign.</p>',
            'cta_text' => 'Read more',
            'cta_url' => url('/'),
        ]);
    }
}
