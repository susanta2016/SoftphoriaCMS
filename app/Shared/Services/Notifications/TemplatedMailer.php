<?php

namespace App\Shared\Services\Notifications;

use App\Enums\EmailRecipientType;
use App\Models\EmailTemplate;
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
 */
class TemplatedMailer
{
    public function __construct(
        private readonly MailSettingsApplier $mailSettings,
        private readonly SettingsRepository $settings,
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

        return new TemplatedNotificationMail(
            $this->substitute($template->subject, $variables),
            $this->substitute($template->html_body, $variables),
            filled($template->text_body) ? $this->substitute($template->text_body, $variables) : null,
            $this->settings->get('email', 'reply_to_email'),
            $this->settings->get('email', 'reply_to_name'),
        );
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
