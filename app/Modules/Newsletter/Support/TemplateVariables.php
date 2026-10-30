<?php

namespace App\Modules\Newsletter\Support;

/**
 * The complete, fixed list of placeholders a newsletter template or
 * campaign content may use: `{{ name }}`, optionally with a fallback for
 * empty values, `{{ subscriber.first_name | "there" }}`. Anything else is
 * rejected when the template is saved and rendered as nothing if it still
 * reaches a send. Values are plain strings looked up by exact name — there
 * is no expression syntax and no path to any application object.
 */
final class TemplateVariables
{
    public const PATTERN = '/\{\{\s*([a-z_]+(?:\.[a-z_]+)?)\s*(?:\|\s*"([^"{}]*)"\s*)?\}\}/i';

    /**
     * Same for every recipient — resolved once, into the campaign snapshot.
     *
     * @var array<string, string>
     */
    public const CAMPAIGN = [
        'campaign_title' => 'The campaign subject line',
        'campaign_content' => 'The campaign\'s own content (HTML)',
        'campaign.subject' => 'The email subject',
        'campaign.preheader' => 'The preheader (inbox preview) text',
        'preheader' => 'Same as campaign.preheader',
        'campaign.cta_text' => 'Call-to-action button text',
        'campaign.cta_url' => 'Call-to-action link',
        'campaign.cta_button' => 'A ready-made call-to-action button (empty when no CTA is set)',
        'site_name' => 'The site name (Settings > General)',
        'site_url' => 'The site\'s home page address',
        'sender.name' => 'The sender name',
        'sender.email' => 'The sender email address',
        'sender.address' => 'The postal address from Newsletter Settings',
        'current_year' => 'The current year',
        'footer' => 'The default footer from Newsletter Settings (includes the unsubscribe link)',
    ];

    /**
     * Different for each recipient — left as markers in the snapshot and
     * filled per send (NewsletterRenderer::personalize()).
     *
     * @var array<string, string>
     */
    public const RECIPIENT = [
        'subscriber.first_name' => 'First name',
        'subscriber.last_name' => 'Last name',
        'subscriber.full_name' => 'First and last name',
        'subscriber.email' => 'Email address',
        'unsubscribe_url' => 'This recipient\'s personal unsubscribe link (required)',
    ];

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::CAMPAIGN + self::RECIPIENT;
    }

    /**
     * Placeholder names used in $content that aren't on the list.
     *
     * @return array<int, string>
     */
    public static function unknownIn(string $content): array
    {
        preg_match_all(self::PATTERN, $content, $matches);

        return array_values(array_unique(array_filter(
            $matches[1],
            fn (string $name): bool => ! array_key_exists(mb_strtolower($name), self::all()),
        )));
    }

    /**
     * Whether $content carries an unsubscribe link, directly or through
     * the default footer.
     */
    public static function hasUnsubscribeLink(string $content): bool
    {
        preg_match_all(self::PATTERN, $content, $matches);

        $names = array_map('mb_strtolower', $matches[1]);

        return in_array('unsubscribe_url', $names, true) || in_array('footer', $names, true);
    }

    /**
     * Admin-facing reference text for the editors' helper text.
     */
    public static function reference(): string
    {
        return collect(self::all())
            ->map(fn (string $description, string $name): string => "{{ {$name} }} — {$description}")
            ->implode("\n");
    }
}
