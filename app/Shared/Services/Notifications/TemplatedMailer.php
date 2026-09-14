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
            self::renderEmailHtml($this->substitute($template->html_body, $variables)),
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

    /**
     * Converts an admin-authored body's plain-text line breaks into real
     * HTML structure — the "HTML Body" field (EditEmailTemplate) is a plain
     * Textarea, not a rich-text editor, so an admin typing ordinary
     * paragraphs separated by a blank line produces literal newline
     * characters with no markup around them. Inserted directly as HTML,
     * consecutive whitespace collapses per the HTML spec and every
     * paragraph runs together as one block — this is that fix, applied once
     * here so every current and future template gets it automatically.
     *
     * Works paragraph-by-paragraph (split on blank lines first, then
     * checked individually) rather than an all-or-nothing check on the
     * whole body — real admin-authored content is often a *mix*: some
     * paragraphs already hand-wrapped in <p> (e.g. from an earlier partial
     * edit), others still plain text relying on this conversion. An
     * all-or-nothing "skip everything if a <p> appears anywhere" check
     * (the original version of this method) meant a single already-wrapped
     * paragraph anywhere in the body silently disabled formatting for
     * every plain-text paragraph after it — confirmed in production on the
     * "New Registration / Welcome" template, whose first two paragraphs
     * were hand-wrapped in <p> but the rest of the body (a plain-text
     * bullet list and closing paragraphs) was not, and rendered as one
     * collapsed block. Each paragraph is now judged independently: one
     * already containing block-level HTML (<p>, <div>, <br>, a
     * list/table/heading, etc.) is left completely untouched; a plain-text
     * paragraph is wrapped in <p> with its own single line breaks
     * converted to <br>. A plain inline tag like <a>/<strong> does NOT
     * count as already structured — a paragraph containing a link still
     * gets wrapped the same as any other plain paragraph.
     *
     * Public + static for the same reason substitute() is: EditEmailTemplate's
     * live preview must render through this exact function, never a second
     * reimplementation that could drift out of sync with real sends.
     */
    public static function formatHtmlBody(string $html): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim($html));

        return collect($paragraphs)
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter(fn (string $paragraph): bool => $paragraph !== '')
            ->map(function (string $paragraph): string {
                if (preg_match('/<(p|div|br|table|tr|td|th|ul|ol|li|h[1-6]|blockquote|hr)\b/i', $paragraph)) {
                    return $paragraph;
                }

                return '<p style="margin:0 0 1em 0;">'.nl2br($paragraph).'</p>';
            })
            ->implode("\n");
    }

    /**
     * The one shared email-rendering path — resources/views/emails/layout.blade.php
     * provides the surrounding email-safe presentation (background, centered
     * table-based container, max width, padding, font stack, heading/link
     * defaults) around whatever the admin authored in `html_body`. Both a
     * real send (renderAsMailable() above) and EditEmailTemplate's admin
     * preview call this exact function, so the two can never drift apart —
     * the only difference between them is the Filament chrome (border,
     * "Preview" label, scroll container) wrapped *around* this function's
     * output, never inside it.
     *
     * Always runs the content through formatHtmlBody() first — composed
     * here rather than left to each caller, so it's structurally impossible
     * to reach the layout with unformatted (collapsed-paragraph) content.
     *
     * The layout view never receives raw admin content as Blade source —
     * $content is a plain, already-substituted PHP string interpolated via
     * {!! !!}, never compiled as a second Blade template (see substitute()'s
     * own docblock on why admin content is never passed to Blade::render()).
     */
    public static function renderEmailHtml(string $rawHtmlBody): string
    {
        return view('emails.layout', [
            'content' => self::formatHtmlBody($rawHtmlBody),
        ])->render();
    }
}
