<?php

namespace App\Tools\SeoChecker\Checks;

use App\Tools\SeoChecker\AuditContext;
use App\Tools\SeoChecker\AuditException;
use App\Tools\SeoChecker\Finding;
use App\Tools\SeoChecker\PageDocument;
use App\Tools\SeoChecker\StagingHosts;

/**
 * Social sharing: Open Graph and Twitter/X card tags, plus whether og:image
 * loads and how big it is. Also leaves the values for the share preview
 * (detected values only — missing ones stay null).
 */
class SocialChecks extends CheckGroup
{
    public const CATEGORY = 'social';

    public const LABEL = 'Social sharing';

    private const IMAGE_MAX_BYTES = 8_000_000;

    public function run(AuditContext $context): array
    {
        $doc = $context->doc;
        $context->shared['preview'] = [
            'title' => $doc->firstMeta('og:title') ?? $doc->firstMeta('twitter:title') ?? (($doc->titles[0] ?? '') ?: null),
            'description' => $doc->firstMeta('og:description') ?? $doc->firstMeta('twitter:description') ?? $doc->firstMeta('description'),
            'site_name' => $doc->firstMeta('og:site_name'),
            'domain' => $context->host($context->absolute($doc->firstMeta('og:url')) ?? $context->finalUrl()),
            'image' => null,
            'image_width' => null,
            'image_height' => null,
            'card' => $doc->firstMeta('twitter:card'),
        ];

        return [
            $this->tag($context, 'og:title', 'social.og_title', 'Open Graph title (og:title)',
                'og:title is the headline Facebook, LinkedIn, WhatsApp and other apps show when the page is shared. Without it they guess, usually from the <title>.',
                'Add <meta property="og:title" content="…">. WordPress SEO plugins set it from the SEO title.'),
            $this->tag($context, 'og:description', 'social.og_description', 'Open Graph description (og:description)',
                'og:description is the short text under the headline in link previews.',
                'Add <meta property="og:description" content="…">, normally the same as or close to the meta description.'),
            $this->ogUrl($context),
            $this->ogImage($context),
            $this->twitterCard($context),
        ];
    }

    private function tag(AuditContext $c, string $key, string $id, string $label, string $why, string $fix): Finding
    {
        $value = $c->doc->firstMeta($key);

        if ($value === null) {
            return $this->warning($id, "No {$label}", "No <meta property=\"{$key}\"> in the HTML.", $why, $fix);
        }

        return $this->passed($id, "{$label} set", "{$key}: {$this->quote($value, 300)}", $why);
    }

    private function ogUrl(AuditContext $c): Finding
    {
        $why = 'og:url is the address that likes and shares are counted against; it should be the page\'s canonical address.';
        $raw = $c->doc->firstMeta('og:url');

        if ($raw === null) {
            return $this->warning('social.og_url', 'No Open Graph URL (og:url)', 'No <meta property="og:url"> in the HTML.', $why,
                'Add <meta property="og:url" content="…"> with the page\'s canonical https:// address.');
        }

        $url = $c->absolute($raw);
        $canonical = $c->shared['canonical'] ?? null;
        $evidence = ["og:url: {$raw}"];

        if ($url === null || ! preg_match('#^https?://#i', $raw)) {
            return $this->warning('social.og_url', 'og:url isn\'t a full address', $evidence, $why, 'Use the full absolute address, starting with https://.');
        }
        if (! $c->isStaging() && StagingHosts::looksLikeStaging($c->host($url)) && $c->host($url) !== $c->host()) {
            return $this->warning('social.og_url', 'og:url points to a staging address', $evidence, $why, 'Change og:url to the live address (usually fixed by updating the site URL in your CMS or SEO plugin).');
        }
        if ($canonical !== null && ! $c->sameUrl($url, $canonical)) {
            $evidence[] = "Canonical: {$canonical}";

            return $this->warning('social.og_url', 'og:url differs from the canonical URL', $evidence, $why, 'Make og:url the same as the canonical URL.');
        }

        return $this->passed('social.og_url', 'Open Graph URL set', $evidence, $why);
    }

    private function ogImage(AuditContext $c): Finding
    {
        $why = 'og:image is the picture in link previews. Without a working one, shared links show no image or an unrelated one, and get noticeably fewer clicks.';
        $fix = 'Add <meta property="og:image" content="https://…/share.jpg"> pointing to a JPG or PNG of 1200×630 px. In WordPress, set a default social image in your SEO plugin.';
        $raw = $c->doc->firstMeta('og:image') ?? $c->doc->firstMeta('og:image:url') ?? $c->doc->firstMeta('og:image:secure_url');
        $twitterImage = $c->doc->firstMeta('twitter:image');

        if ($raw === null) {
            return $this->warning('social.og_image', 'No share image (og:image)', array_values(array_filter([
                'No <meta property="og:image"> in the HTML.',
                $twitterImage ? "twitter:image is set ({$twitterImage}), but other apps don't read it." : null,
            ])), $why, $fix);
        }

        $evidence = ["og:image: {$raw}"];
        $url = $c->absolute($raw);

        if ($url === null) {
            return $this->warning('social.og_image', 'og:image isn\'t a valid address', $evidence, $why, $fix);
        }
        if (! preg_match('#^https?://#i', $raw)) {
            $evidence[] = 'Written as a relative address; most platforms need the full https:// address.';
        }

        $result = $c->fetch($url, ['max_redirects' => 3, 'max_bytes' => self::IMAGE_MAX_BYTES, 'accept' => 'image/*,*/*;q=0.5']);

        if ($result instanceof AuditException) {
            return $result->reason === 'too_large'
                ? $this->warning('social.og_image', 'The share image is very large', [...$evidence, 'Larger than 8 MB.'], $why, 'Use a compressed JPG or PNG under about 5 MB (1200×630 px is plenty).')
                : $this->notChecked('social.og_image', 'Share image (og:image)', [...$evidence, $this->failure($result)], $why);
        }

        $evidence[] = "→ HTTP {$result->status}".($result->contentType() ? ", {$result->contentType()}" : '');
        $size = $result->successful() ? @getimagesizefromstring($result->body) : false;

        if (! $result->successful() || $size === false) {
            return $this->warning('social.og_image', 'The share image doesn\'t load as an image', $evidence, $why, $fix);
        }

        [$width, $height] = $size;
        $evidence[] = "{$width}×{$height} px, ".number_format($result->bytes / 1024, 0).' KB';
        $c->shared['preview']['image'] = str_starts_with($result->finalUrl, 'https://') ? $result->finalUrl : null;
        $c->shared['preview']['image_width'] = $width;
        $c->shared['preview']['image_height'] = $height;

        $problems = [];
        if ($width < 600 || $height < 315) {
            $problems[] = 'Smaller than 600×315 px, so platforms may show a small thumbnail instead of a large preview.';
        }
        if (! str_starts_with($result->finalUrl, 'https://')) {
            $problems[] = 'Served over plain HTTP.';
        }
        if (! preg_match('#^https?://#i', $raw)) {
            $problems[] = 'Relative address.';
        }

        return $problems === []
            ? $this->passed('social.og_image', 'Share image loads', $evidence, $why, 'Nothing to do.', '1200×630 px (1.91:1) is the commonly recommended size for large previews.')
            : $this->warning('social.og_image', 'The share image needs attention', [...$evidence, ...$problems], $why, $fix);
    }

    private function twitterCard(AuditContext $c): Finding
    {
        $why = 'twitter:card chooses how links look on X (Twitter). Without it, X falls back to a small summary card; title, description and image fall back to the Open Graph tags.';
        $card = $c->doc->firstMeta('twitter:card');
        $explicit = array_values(array_filter(array_map(
            fn (string $key): ?string => ($v = $c->doc->firstMeta($key)) !== null ? "{$key}: ".PageDocument::clean($v, 120) : null,
            ['twitter:title', 'twitter:description', 'twitter:image'],
        )));
        $note = $explicit === [] ? 'No twitter:title/description/image — X uses the Open Graph tags instead.' : null;

        if ($card === null) {
            return $this->warning('social.twitter_card', 'No Twitter/X card type', array_values(array_filter(['No <meta name="twitter:card"> in the HTML.', ...$explicit, $note])), $why,
                'Add <meta name="twitter:card" content="summary_large_image"> for a large image preview.');
        }

        $evidence = array_values(array_filter(["twitter:card: {$card}", ...$explicit, $note]));

        if (! in_array(strtolower($card), ['summary', 'summary_large_image', 'app', 'player'], true)) {
            return $this->warning('social.twitter_card', 'Unknown Twitter/X card type', $evidence, $why, 'Use summary_large_image (or summary).');
        }

        return $this->passed('social.twitter_card', 'Twitter/X card set', $evidence, $why);
    }
}
