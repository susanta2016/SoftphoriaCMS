<?php

namespace App\Modules\Newsletter\Support;

use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use App\Shared\Mail\BrandedEmailLayout;
use App\Shared\Services\Settings\SettingsRepository;
use DOMDocument;
use DOMElement;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Turns a template + campaign into email HTML and plain text. Two stages:
 *
 * snapshot() — everything that is the same for every recipient: campaign
 * placeholders (HTML-escaped; campaign content and the footer are HTML by
 * design), the default footer when the template has no unsubscribe link,
 * the hidden preheader, the template CSS (inlined, and kept in <head> for
 * media queries), sanitizing (EmailHtmlSanitizer) and, when enabled, click
 * tracking + the open pixel. Recipient placeholders become URI-safe markers
 * so they survive the DOM passes inside href attributes.
 *
 * personalize() — fills those markers for one recipient. Cheap string
 * replacement, run once per send.
 *
 * Placeholder substitution is a plain lookup in TemplateVariables'
 * allowlist; template content is never passed to Blade, eval or anything
 * that could execute it.
 */
class NewsletterRenderer
{
    private const MARKER = '/NLVAR([0-9a-f]+)NLEND/';

    public function __construct(
        private readonly NewsletterSettings $settings,
        private readonly SettingsRepository $siteSettings,
    ) {}

    public function snapshot(NewsletterCampaign $campaign, NewsletterTemplate $template, bool $tracking = true): RenderedEmail
    {
        $html = $template->html_content;

        if (! TemplateVariables::hasUnsubscribeLink($html) && ! TemplateVariables::hasUnsubscribeLink((string) $campaign->content)) {
            $footer = '<div style="padding:24px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#6b7280;text-align:center;">{{ footer }}</div>';
            $html = str_contains($html, '</body>') ? str_replace('</body>', $footer.'</body>', $html) : $html.$footer;
        }

        // Expand the two HTML-valued placeholders first, so placeholders
        // inside the campaign content and the footer are resolved by the
        // main pass below.
        $html = preg_replace_callback(TemplateVariables::PATTERN, function (array $match) use ($campaign): string {
            return match (mb_strtolower($match[1])) {
                'campaign_content' => (string) $campaign->content,
                'footer' => $this->settings->footerHtml(),
                default => $match[0],
            };
        }, $html);

        $values = $this->campaignValues($campaign);

        $html = preg_replace_callback(TemplateVariables::PATTERN, function (array $match) use ($values): string {
            $name = mb_strtolower($match[1]);
            $fallback = $match[2] ?? '';

            if (array_key_exists($name, TemplateVariables::RECIPIENT)) {
                return self::marker($name, $fallback);
            }

            $value = $values[$name] ?? '';

            return $value !== '' ? $value : e($fallback);
        }, $html);

        $html = $this->withHeadStyles($html, (string) $template->css_content);
        $html = (new CssToInlineStyles)->convert($html);

        $document = self::loadDocument($html);
        EmailHtmlSanitizer::sanitizeDocument($document);
        $this->insertPreheader($document, (string) $campaign->preheader);

        $text = BrandedEmailLayout::htmlToText($this->bodyForText($document));

        $links = [];

        if ($tracking && $campaign->track_clicks) {
            $links = $this->trackLinks($document);
        }

        if ($tracking && $campaign->track_opens) {
            $this->appendOpenPixel($document);
        }

        return new RenderedEmail((string) $document->saveHTML(), $text, $links);
    }

    public function personalize(RenderedEmail $snapshot, NewsletterCampaignRecipient $recipient): RenderedEmail
    {
        $values = [
            'subscriber.first_name' => (string) $recipient->first_name,
            'subscriber.last_name' => (string) $recipient->last_name,
            'subscriber.full_name' => trim($recipient->first_name.' '.$recipient->last_name),
            'subscriber.email' => $recipient->email,
            'unsubscribe_url' => route('newsletter.unsubscribe', ['token' => $recipient->token]),
            'recipient.token' => $recipient->token,
        ];

        return $this->fillMarkers($snapshot, $values);
    }

    /**
     * The subject line for one recipient (or the sample subscriber). Plain
     * text — nothing is HTML-escaped, and line breaks are dropped so a
     * value can never inject a header.
     *
     * @param  array{first_name?: ?string, last_name?: ?string, email?: ?string}  $subscriber
     */
    public function subject(NewsletterCampaign $campaign, array $subscriber): string
    {
        $first = (string) ($subscriber['first_name'] ?? '');
        $last = (string) ($subscriber['last_name'] ?? '');
        $values = [
            'subscriber.first_name' => $first,
            'subscriber.last_name' => $last,
            'subscriber.full_name' => trim("{$first} {$last}"),
            'subscriber.email' => (string) ($subscriber['email'] ?? ''),
            'site_name' => (string) $this->siteSettings->get('general', 'site_name', config('app.name')),
            'sender.name' => (string) $campaign->from_name,
            'current_year' => (string) now()->year,
        ];

        $subject = preg_replace_callback(TemplateVariables::PATTERN, function (array $match) use ($values): string {
            $value = $values[mb_strtolower($match[1])] ?? '';

            return $value !== '' ? $value : ($match[2] ?? '');
        }, (string) $campaign->subject);

        return trim(preg_replace('/[\r\n\t]+/', ' ', $subject));
    }

    /**
     * Fully rendered sample for the admin preview and test emails —
     * never tracked, and its unsubscribe link goes nowhere.
     *
     * @param  array{first_name?: ?string, last_name?: ?string, email?: ?string}  $subscriber
     */
    public function preview(NewsletterCampaign $campaign, NewsletterTemplate $template, array $subscriber, ?string $unsubscribeUrl = null): RenderedEmail
    {
        $first = (string) ($subscriber['first_name'] ?? '');
        $last = (string) ($subscriber['last_name'] ?? '');

        return $this->fillMarkers($this->snapshot($campaign, $template, tracking: false), [
            'subscriber.first_name' => $first,
            'subscriber.last_name' => $last,
            'subscriber.full_name' => trim("{$first} {$last}"),
            'subscriber.email' => (string) ($subscriber['email'] ?? ''),
            'unsubscribe_url' => $unsubscribeUrl ?? url('/#newsletter-preview-unsubscribe'),
            'recipient.token' => 'preview',
        ]);
    }

    /**
     * Sample personalization data for previews.
     *
     * @return array{first_name: string, last_name: string, email: string}
     */
    public static function sampleSubscriber(): array
    {
        return ['first_name' => 'Alex', 'last_name' => 'Sample', 'email' => 'alex.sample@example.com'];
    }

    /**
     * @return array<string, string>
     */
    private function campaignValues(NewsletterCampaign $campaign): array
    {
        // A personalized subject ("Hi {{ subscriber.first_name }}") shown in
        // the body via {{ campaign_title }} keeps its recipient placeholders.
        $subject = e(preg_replace_callback(TemplateVariables::PATTERN, fn (array $match): string => array_key_exists(mb_strtolower($match[1]), TemplateVariables::RECIPIENT)
            ? self::marker(mb_strtolower($match[1]), $match[2] ?? '')
            : '', (string) $campaign->subject));
        $preheader = e((string) $campaign->preheader);
        $ctaText = e((string) $campaign->cta_text);
        $ctaUrl = e((string) $campaign->cta_url);

        return [
            'campaign_title' => $subject,
            'campaign.subject' => $subject,
            'campaign.preheader' => $preheader,
            'preheader' => $preheader,
            'campaign.cta_text' => $ctaText,
            'campaign.cta_url' => $ctaUrl,
            'campaign.cta_button' => $ctaUrl !== '' && $ctaText !== '' ? self::ctaButton($ctaUrl, $ctaText) : '',
            'site_name' => e((string) $this->siteSettings->get('general', 'site_name', config('app.name'))),
            'site_url' => e(url('/')),
            'sender.name' => e((string) $campaign->from_name),
            'sender.email' => e((string) $campaign->from_email),
            'sender.address' => nl2br(e($this->settings->senderAddress()), false),
            'current_year' => (string) now()->year,
        ];
    }

    /**
     * Table-based ("bulletproof") button — renders as a button in Outlook too.
     */
    private static function ctaButton(string $escapedUrl, string $escapedText): string
    {
        return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:16px 0;"><tr>'
            .'<td style="border-radius:6px;background-color:#2563eb;">'
            .'<a href="'.$escapedUrl.'" style="display:inline-block;padding:12px 24px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:6px;">'
            .$escapedText.'</a></td></tr></table>';
    }

    private static function marker(string $name, string $fallback): string
    {
        return 'NLVAR'.bin2hex($name."\x1F".$fallback).'NLEND';
    }

    /**
     * @param  array<string, string>  $values
     */
    private function fillMarkers(RenderedEmail $email, array $values): RenderedEmail
    {
        $fill = function (string $content, bool $escape) use ($values): string {
            return preg_replace_callback(self::MARKER, function (array $match) use ($values, $escape): string {
                [$name, $fallback] = array_pad(explode("\x1F", (string) hex2bin($match[1]), 2), 2, '');
                $value = ($values[$name] ?? '') !== '' ? $values[$name] : $fallback;

                return $escape ? e($value) : $value;
            }, $content);
        };

        return new RenderedEmail($fill($email->html, true), $fill($email->text, false), $email->links);
    }

    private function withHeadStyles(string $html, string $css): string
    {
        if (trim($css) === '') {
            return $html;
        }

        // A </style> inside the CSS would close the block early and let the
        // rest be parsed as HTML.
        $style = '<style type="text/css">'.str_ireplace('</style', '', $css).'</style>';

        if (preg_match('/<\/head>/i', $html)) {
            return preg_replace('/<\/head>/i', $style.'</head>', $html, 1);
        }

        return $style.$html;
    }

    private function insertPreheader(DOMDocument $document, string $preheader): void
    {
        $body = $document->getElementsByTagName('body')->item(0);

        if ($preheader === '' || ! $body instanceof DOMElement) {
            return;
        }

        $div = $document->createElement('div');
        $div->setAttribute('data-nl-preheader', '');
        $div->setAttribute('style', 'display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;');
        // Trailing zero-width non-joiners stop clients from pulling body
        // text into the inbox preview after the preheader.
        $div->appendChild($document->createTextNode($preheader.str_repeat(" \u{200C}", 40)));

        $body->insertBefore($div, $body->firstChild);
    }

    /**
     * The visible body only — no <head>, <style> or hidden preheader in the
     * plain-text part.
     */
    private function bodyForText(DOMDocument $document): string
    {
        $copy = clone $document;

        foreach (['head', 'style', 'title'] as $tag) {
            foreach (iterator_to_array($copy->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        foreach (iterator_to_array((new \DOMXPath($copy))->query('//*[@data-nl-preheader]')) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $body = $copy->getElementsByTagName('body')->item(0);

        return $body !== null ? (string) $copy->saveHTML($body) : (string) $copy->saveHTML();
    }

    /**
     * Rewrites http(s) links to the click-tracking redirect. Links holding a
     * recipient marker (the unsubscribe link), mailto:/tel: and anchors are
     * left alone.
     *
     * @return array<string, string>
     */
    private function trackLinks(DOMDocument $document): array
    {
        $links = [];

        foreach (iterator_to_array($document->getElementsByTagName('a')) as $anchor) {
            /** @var DOMElement $anchor */
            $href = trim($anchor->getAttribute('href'));

            if ($href === '' || preg_match(self::MARKER, $href) || ! preg_match('#^https?://#i', $href)) {
                continue;
            }

            $hash = substr(hash('sha256', $href), 0, 16);
            $links[$hash] = $href;

            $anchor->setAttribute('href', route('newsletter.track.click', [
                'token' => self::marker('recipient.token', ''),
                'link' => $hash,
            ]));
        }

        return $links;
    }

    private function appendOpenPixel(DOMDocument $document): void
    {
        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return;
        }

        $img = $document->createElement('img');
        $img->setAttribute('src', route('newsletter.track.open', ['token' => self::marker('recipient.token', '')]));
        $img->setAttribute('width', '1');
        $img->setAttribute('height', '1');
        $img->setAttribute('alt', '');
        $img->setAttribute('style', 'display:block;width:1px;height:1px;border:0;');

        $body->appendChild($img);
    }

    private static function loadDocument(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8'), LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }
}
