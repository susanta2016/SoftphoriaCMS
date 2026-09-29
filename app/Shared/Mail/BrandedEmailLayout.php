<?php

namespace App\Shared\Mail;

use App\Models\Media;
use App\Models\Page;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Storage;

/**
 * Site branding for the shared email layout (resources/views/emails/layout.blade.php)
 * — the logo header and footer every templated notification is wrapped in,
 * so stored Email Templates hold only their own content and never a copy
 * of the header/footer. Supplies the same brand data to the HTML layout
 * (via TemplatedMailer::renderEmailHtml(), used by real sends and the
 * admin preview alike) and to the plain-text part.
 *
 * Everything comes from Website Setup rather than the request host, so a
 * mail sent from a queue/CLI still links to the configured site: the
 * header logo, and the footer's sub-heading/copyright text exactly as the
 * public site footer (components/site/footer.blade.php) resolves them.
 */
class BrandedEmailLayout
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return array{siteName: string, subheading: string, logoUrl: ?string, siteUrl: string, contactUrl: string, privacyUrl: ?string, copyright: string}
     */
    public function brand(): array
    {
        $general = $this->settings->all('general');
        $footer = $this->settings->all('footer');

        $siteName = (string) (($general['site_name'] ?? null) ?: config('app.name'));
        $siteUrl = rtrim((string) (($general['site_url'] ?? null) ?: config('app.url')), '/');
        $hasPrivacyPage = Page::query()->published()->where('slug', 'privacy-policy')->exists();

        // Same fallbacks and {year} token as the public site footer.
        $subheading = ($footer['subheading'] ?? null)
            ?: 'A creative home for music, writing, reflection, thinking, and community.';
        $copyright = str_replace(
            '{year}',
            (string) now()->year,
            ($footer['copyright_text'] ?? null) ?: '© {year} '.$siteName.'. All rights reserved.',
        );

        return [
            'siteName' => $siteName,
            'subheading' => $subheading,
            'logoUrl' => $this->logoUrl($general['logo_media_id'] ?? null, $siteUrl),
            'siteUrl' => $siteUrl,
            'contactUrl' => $siteUrl.route('contact.index', absolute: false),
            'privacyUrl' => $hasPrivacyPage ? $siteUrl.route('pages.show', 'privacy-policy', absolute: false) : null,
            'copyright' => $copyright,
        ];
    }

    /**
     * The plain-text part: the body followed by a short text footer
     * mirroring the HTML one.
     */
    public function text(string $bodyText): string
    {
        $brand = $this->brand();

        $footer = array_filter([
            '--',
            $brand['siteName'],
            'Website: '.$brand['siteUrl'],
            'Contact: '.$brand['contactUrl'],
            $brand['privacyUrl'] ? 'Privacy: '.$brand['privacyUrl'] : null,
            $brand['copyright'],
        ]);

        return trim($bodyText)."\n\n".implode("\n", $footer)."\n";
    }

    /**
     * A readable plain-text rendering of a template's HTML body, for the
     * templates whose admin-editable Plain-Text Fallback is empty — so no
     * notification is ever sent HTML-only. Links keep their URL visible
     * ("Verify my email: https://…"), since a text client can't follow an
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
     * The same uploaded logo the site header shows. SVG is skipped — Gmail
     * and Outlook don't render it — and the layout falls back to the site
     * name as text instead.
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
