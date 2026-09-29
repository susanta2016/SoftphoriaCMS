<?php

namespace App\Shared\Mail;

use App\Models\Media;
use App\Models\Page;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * The one shared Softphoria email frame (EMAIL-001) — branded header,
 * content card and footer wrapped around a template's already-substituted
 * body. Stored Email Templates hold only their own content; the header and
 * footer live here, so they are never copied into (or drift between)
 * individual rows. Used by TemplatedMailer for real sends and by
 * EditEmailTemplate's preview, so the admin sees exactly the real frame.
 *
 * The body is inserted as-is and never compiled by Blade (see
 * TemplatedMailer::substitute()). Brand details come from Website Setup
 * (site name, tagline, logo, site URL) rather than the request host, so a
 * mail sent from a queue/CLI still links to the configured site.
 */
class BrandedEmailLayout
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function html(string $subject, string $bodyHtml): string
    {
        return view('mail.branded', [
            'subject' => $subject,
            'body' => new HtmlString($bodyHtml),
            ...$this->brand(),
        ])->render();
    }

    public function text(string $bodyText): string
    {
        $brand = $this->brand();

        $footer = array_filter([
            '--',
            $brand['siteName'].($brand['tagline'] ? ' — '.$brand['tagline'] : ''),
            'Website: '.$brand['siteUrl'],
            'Contact: '.$brand['contactUrl'],
            $brand['privacyUrl'] ? 'Privacy: '.$brand['privacyUrl'] : null,
            "© {$brand['year']} {$brand['siteName']}. All rights reserved.",
        ]);

        return trim($bodyText)."\n\n".implode("\n", $footer)."\n";
    }

    /**
     * A readable plain-text rendering of a template's HTML body, for the
     * templates whose admin-editable Plain-Text Fallback is empty — so no
     * notification is ever sent HTML-only. Links keep their URL visible
     * ("Verify My Email: https://…"), since a text client can't follow an
     * anchor.
     */
    public static function htmlToText(string $html): string
    {
        $text = preg_replace_callback(
            '/<a\s[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is',
            function (array $match): string {
                $url = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5);
                $label = trim(strip_tags($match[3]));
                $target = str_starts_with($url, 'mailto:') ? substr($url, 7) : $url;

                return $label === '' || html_entity_decode($label, ENT_QUOTES | ENT_HTML5) === $target
                    ? $target
                    : "{$label}: {$target}";
            },
            $html,
        );

        // nl2br() keeps the original newline after each <br> — consume it.
        $text = preg_replace('/<br\s*\/?>\r?\n?/i', "\n", $text);
        $text = preg_replace('/<li[^>]*>/i', '- ', $text);
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr|table|ul|ol|blockquote)>/i', "\n\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);

        $lines = array_map(fn (string $line): string => trim(preg_replace('/[ \t]+/', ' ', $line)), explode("\n", $text));

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    /**
     * @return array{siteName: string, tagline: ?string, logoUrl: ?string, siteUrl: string, contactUrl: string, privacyUrl: ?string, year: string}
     */
    private function brand(): array
    {
        $general = $this->settings->all('general');
        $siteUrl = rtrim((string) (($general['site_url'] ?? null) ?: config('app.url')), '/');

        $privacy = Page::query()->published()->where('slug', 'privacy-policy')->exists();

        return [
            'siteName' => (string) (($general['site_name'] ?? null) ?: config('app.name')),
            'tagline' => ($general['tagline'] ?? null) ?: null,
            'logoUrl' => $this->logoUrl($general['logo_media_id'] ?? null, $siteUrl),
            'siteUrl' => $siteUrl,
            'contactUrl' => $siteUrl.route('contact.index', absolute: false),
            'privacyUrl' => $privacy ? $siteUrl.route('pages.show', 'privacy-policy', absolute: false) : null,
            'year' => now()->format('Y'),
        ];
    }

    /**
     * The same uploaded logo the site header shows. SVG is skipped — Gmail
     * and Outlook don't render it — and the layout falls back to the text
     * wordmark instead.
     */
    private function logoUrl(mixed $mediaId, string $siteUrl): ?string
    {
        $logo = $mediaId ? Media::find($mediaId) : null;

        if (! $logo || str_contains((string) $logo->mime_type, 'svg')) {
            return null;
        }

        $url = Storage::disk($logo->disk)->url($logo->path);

        return str_starts_with($url, 'http') ? $url : $siteUrl.'/'.ltrim($url, '/');
    }
}
