<?php

namespace App\Shared\Support\Seo;

use App\Models\Media;
use App\Models\SeoMetadata;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Storage;

/**
 * Turns an optional SeoMetadata row (Pages module's admin-editable SEO tab,
 * Database Specification §18.6) plus content-derived fallbacks (a page's
 * own title/summary/featured image) into the full set of values every
 * public <head> needs — meta description/robots/keywords, canonical,
 * Open Graph, Twitter Card, and schema.org itemprop tags. One place so
 * HomeController and PageContentRenderer resolve identical output instead
 * of two independent implementations (same reasoning as MediaPicker for
 * media upload/select — see docs/ARCHITECTURE.md §14).
 *
 * Site-wide identifiers that don't belong on a per-page SeoMetadata row
 * (site name, Twitter/X handle, Facebook App ID, default share image) come
 * from Website Setup's General/SEO settings instead — see
 * App\Filament\Pages\Settings.
 */
class SeoTagBuilder
{
    /**
     * @param  array{title: string, description?: ?string, canonical: string, force_canonical?: bool, image?: ?Media, type?: string, published_at?: mixed, modified_at?: mixed, author_name?: ?string}  $fallbacks
     * @param  ?array<string, mixed>  $generalSettings  Pass the caller's own
     *         already-fetched SettingsRepository::all('general') (e.g.
     *         HomeController, which needs it anyway for site_name/tagline/
     *         logo) to avoid a second, identical settings query here.
     * @return array<string, mixed>
     */
    public static function build(?SeoMetadata $seo, array $fallbacks, ?array $generalSettings = null): array
    {
        $general = $generalSettings ?? app(SettingsRepository::class)->all('general');

        $title = $seo?->meta_title ?: $fallbacks['title'];
        $description = self::flatten($seo?->meta_description ?: ($fallbacks['description'] ?? null));

        $defaultImage = self::imageUrl($fallbacks['image'] ?? null)
            ?? self::imageUrl(self::defaultShareImage($general));

        $ogImage = self::imageUrl($seo?->ogImage) ?: $defaultImage;
        $twitterImage = self::imageUrl($seo?->twitterImage) ?: $ogImage;

        $siteName = $general['site_name'] ?? config('app.name');
        $canonical = ($fallbacks['force_canonical'] ?? false)
            ? $fallbacks['canonical']
            : ($seo?->canonical_url ?: $fallbacks['canonical']);

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $seo?->keywords,
            'canonical' => $canonical,
            'robots' => $seo?->robots ?: 'index, follow',
            'site_name' => $siteName,
            'og_title' => $seo?->og_title ?: $title,
            'og_description' => self::flatten($seo?->og_description) ?: $description,
            'og_image' => $ogImage,
            'og_type' => $fallbacks['type'] ?? 'website',
            'twitter_card' => $twitterImage ? 'summary_large_image' : 'summary',
            'twitter_site' => self::normalizeHandle($general['twitter_handle'] ?? null),
            'twitter_title' => $seo?->twitter_title ?: $title,
            'twitter_description' => self::flatten($seo?->twitter_description) ?: $description,
            'twitter_image' => $twitterImage,
            'fb_app_id' => $general['fb_app_id'] ?? null,
            'structured_data' => $seo?->structured_data ?: self::defaultStructuredData(
                $fallbacks,
                $general,
                $title,
                $description,
                $ogImage,
                $siteName,
                $canonical,
            ),
        ];
    }

    /**
     * A hand-set seo.structured_data (there's no admin field for it yet,
     * but the column/pipeline is ready — see SeoMetadata) always wins.
     * Otherwise every page gets real schema.org JSON-LD by default rather
     * than none at all: WebSite+Organization for the homepage, Article for
     * a content page — instead of only the legacy itemprop microdata tags
     * (see head-tags.blade.php) that alone are not what modern search
     * engines treat as structured data.
     *
     * @param  array<string, mixed>  $fallbacks
     * @param  array<string, mixed>  $general
     * @return array<string, mixed>
     */
    private static function defaultStructuredData(
        array $fallbacks,
        array $general,
        string $title,
        ?string $description,
        ?string $image,
        string $siteName,
        string $canonical,
    ): array {
        $logoMediaId = $general['logo_media_id'] ?? null;
        $logo = $logoMediaId ? self::imageUrl(Media::find($logoMediaId)) : null;

        $publisher = array_filter([
            '@type' => 'Organization',
            'name' => $siteName,
            'logo' => $logo ? ['@type' => 'ImageObject', 'url' => $logo] : null,
        ]);

        if (($fallbacks['type'] ?? 'website') === 'article') {
            return array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $title,
                'description' => $description,
                'image' => $image,
                'datePublished' => self::isoDate($fallbacks['published_at'] ?? null),
                'dateModified' => self::isoDate($fallbacks['modified_at'] ?? $fallbacks['published_at'] ?? null),
                'author' => filled($fallbacks['author_name'] ?? null)
                    ? ['@type' => 'Person', 'name' => $fallbacks['author_name']]
                    : null,
                'publisher' => $publisher,
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
            ]);
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $canonical,
        ]);
    }

    private static function isoDate(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        return $date instanceof \DateTimeInterface ? $date->toAtomString() : (string) $date;
    }

    /**
     * @param  array<string, mixed>  $general
     */
    private static function defaultShareImage(array $general): ?Media
    {
        $mediaId = $general['default_share_image_media_id'] ?? null;

        return $mediaId ? Media::find($mediaId) : null;
    }

    private static function imageUrl(?Media $media): ?string
    {
        return $media ? Storage::disk($media->disk)->url($media->path) : null;
    }

    /**
     * Collapses newlines/repeated whitespace so a multi-paragraph summary
     * (e.g. the Hero section's subheading) doesn't land as raw line breaks
     * inside a meta content="..." attribute.
     */
    private static function flatten(?string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private static function normalizeHandle(?string $handle): ?string
    {
        if (blank($handle)) {
            return null;
        }

        return '@'.ltrim($handle, '@');
    }
}
