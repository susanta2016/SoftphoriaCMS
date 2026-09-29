<?php

namespace App\Shared\Services\Notifications;

use App\Enums\EmailRecipientType;
use App\Models\EmailTemplate;
use App\Shared\Mail\BrandedEmailLayout;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Services\Settings\MailSettingsApplier;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Mail;

/**
 * The single centralized notification-sending entry point (docs/ARCHITECTURE.md
 * §16.5/§16.7) — every current and future module sends email through this,
 * never Mail::raw()/a bespoke Mailable with hardcoded copy, and never a
 * module-specific template table. Both send() and renderAsMailable() build
 * the same TemplatedNotificationMail — one real Mailable class, so both
 * paths are observable via Mail::fake(), unlike a raw Mail::send() array
 * call (which MailFake silently ignores).
 *
 * Every send is wrapped in the shared BrandedEmailLayout (EMAIL-001): the
 * stored template supplies only its subject + body, the layout adds the
 * Softphoria header/footer, and a plain-text part is always attached —
 * the template's own Plain-Text Fallback when filled, otherwise one
 * derived from its HTML body.
 */
class TemplatedMailer
{
    public function __construct(
        private readonly MailSettingsApplier $mailSettings,
        private readonly SettingsRepository $settings,
        private readonly BrandedEmailLayout $layout,
    ) {}

    /**
     * Silently does nothing if the template is disabled or doesn't exist —
     * a template being off must never throw or block the caller's own
     * workflow.
     *
     * @param  array<string, string>  $variables
     */
    public function send(string $notificationKey, EmailRecipientType $recipientType, string $to, array $variables = []): void
    {
        $mailable = $this->renderAsMailable($notificationKey, $recipientType, $variables);

        if ($mailable === null) {
            return;
        }

        Mail::to($to)->send($mailable);
    }

    /**
     * For integration points that must return a Mailable rather than send
     * directly — e.g. Illuminate\Auth\Notifications\ResetPassword::toMailUsing()
     * (see AppServiceProvider::boot()) — as well as send() above. Returns
     * null on the same disabled/missing conditions in both cases; the
     * caller decides the appropriate fallback.
     *
     * @param  array<string, string>  $variables
     */
    public function renderAsMailable(string $notificationKey, EmailRecipientType $recipientType, array $variables = []): ?TemplatedNotificationMail
    {
        $template = EmailTemplate::query()
            ->where('notification_key', $notificationKey)
            ->where('recipient_type', $recipientType->value)
            ->where('is_enabled', true)
            ->first();

        if (! $template) {
            return null;
        }

        $variables += ['site_name' => (string) $this->settings->get('general', 'site_name', config('app.name'))];

        $this->mailSettings->apply();

        $subject = $this->substitute($template->subject, $variables);
        $heading = self::heading($notificationKey, $recipientType, $variables);

        // Values are escaped for the HTML body: several are visitor-typed
        // (e.g. a Contact message), and must never inject markup/links
        // into a mail sent to an admin or to an arbitrary address.
        $htmlBody = $this->substitute($template->html_body, array_map(fn (mixed $value): string => nl2br(e((string) $value), false), $variables));

        $textBody = filled($template->text_body)
            ? $this->substitute($template->text_body, $variables)
            : BrandedEmailLayout::htmlToText($htmlBody);

        return new TemplatedNotificationMail(
            $subject,
            $this->layout->html($subject, $heading, $htmlBody),
            $this->layout->text($heading, $textBody),
            $this->settings->get('email', 'reply_to_email'),
            $this->settings->get('email', 'reply_to_name'),
        );
    }

    /**
     * The layout's heading line for a key — config-owned copy
     * (config/email_templates.php 'heading': a string, or an array keyed by
     * recipient type) rather than a new stored column, so the template rows
     * and their admin editing stay exactly as they were. Substituted with
     * raw values; the layout escapes it on output. Null = no heading.
     *
     * @param  array<string, string>  $variables
     */
    public static function heading(string $notificationKey, EmailRecipientType $recipientType, array $variables): ?string
    {
        $heading = config("email_templates.{$notificationKey}.heading");

        if (is_array($heading)) {
            $heading = $heading[$recipientType->value] ?? null;
        }

        return filled($heading) ? self::substitute($heading, $variables) : null;
    }

    /**
     * Plain token replacement only — admin-edited template content is never
     * passed to Blade::render() or an eval()-adjacent mechanism, which
     * would let an admin-editable field execute arbitrary server-side code.
     * Public + static so EditEmailTemplate's live preview panel substitutes
     * sample values through the exact same, single implementation rather
     * than a second one that could drift out of sync.
     *
     * @param  array<string, string>  $variables
     */
    public static function substitute(string $content, array $variables): string
    {
        if ($variables === []) {
            return $content;
        }

        return str_replace(
            array_map(fn (string $key): string => "{{{$key}}}", array_keys($variables)),
            array_values($variables),
            $content,
        );
    }
}
