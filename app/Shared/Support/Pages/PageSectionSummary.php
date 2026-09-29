<?php

namespace App\Shared\Support\Pages;

use App\Enums\ModuleKey;
use App\Enums\PageSectionType;
use Illuminate\Support\Str;

/**
 * CMS-001 — the one-line, human-readable summary each page section shows in
 * the admin Page editor's compact section list (PageForm), so an admin can
 * tell what a section holds without opening it. Read-only: derived from the
 * section's existing content_json, never stored.
 *
 * Every branch falls back to "{Type} section" when there's nothing
 * meaningful to show (e.g. a freshly added, still-empty section).
 */
class PageSectionSummary
{
    public const int MAX_LENGTH = 120;

    /**
     * @param  array<string, mixed>  $section  one page section's state: section_type, title, is_enabled, content_json
     */
    public static function label(array $section, ?int $index = null): string
    {
        $type = PageSectionType::tryFrom((string) ($section['section_type'] ?? ''));
        $typeLabel = $type?->getLabel() ?? 'Section';
        $title = trim((string) ($section['title'] ?? ''));

        $parts = array_filter([
            $index === null ? null : str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            $typeLabel,
        ]);
        $label = implode(' · ', $parts);

        if ($title !== '' && strcasecmp($title, $typeLabel) !== 0) {
            $label .= ' — '.$title;
        }

        if (($section['is_enabled'] ?? true) === false) {
            $label .= ' (hidden)';
        }

        return $label;
    }

    /**
     * @param  array<string, mixed>  $section  one page section's state: section_type, title, is_enabled, content_json
     */
    public static function summary(array $section): string
    {
        $type = PageSectionType::tryFrom((string) ($section['section_type'] ?? ''));
        $content = is_array($section['content_json'] ?? null) ? $section['content_json'] : [];

        $summary = $type ? self::forType($type, $content, $section) : null;

        return filled($summary)
            ? Str::limit($summary, self::MAX_LENGTH)
            : ($type?->getLabel() ?? 'Untyped').' section';
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $section
     */
    private static function forType(PageSectionType $type, array $content, array $section): ?string
    {
        $heading = self::heading($content);

        return match ($type) {
            PageSectionType::Hero => self::join([
                $heading,
                self::join(array_map(
                    fn ($stat): string => trim(($stat['value'] ?? '').' '.($stat['label'] ?? '')),
                    self::list($content['stats'] ?? null),
                )),
            ], ' — '),
            PageSectionType::RichText => self::text($content['body'] ?? null),
            PageSectionType::ImageText => $heading ?? self::text($content['text'] ?? null),
            PageSectionType::Faq => self::count(count(self::list($content['items'] ?? null)), 'question'),
            PageSectionType::Quote => self::join([
                self::text($content['quote'] ?? null),
                self::text($content['attribution'] ?? null),
            ], ' — '),
            PageSectionType::Cta => $heading ?? self::text($content['description'] ?? null),
            PageSectionType::Gallery => self::join([
                $heading,
                self::galleryCount($content),
            ]),
            PageSectionType::FeaturedContent => filled($module = ModuleKey::tryFrom((string) ($content['module_key'] ?? ''))?->getLabel())
                ? 'Module: '.$module
                : null,
            PageSectionType::NewsletterSignup => 'Newsletter signup form',
            PageSectionType::ContactForm => self::join([self::text($section['title'] ?? null), 'Contact form']),
            PageSectionType::Testimonials => self::join([$heading, 'Enabled testimonials']),
            PageSectionType::Portfolio => self::join([$heading, 'Up to '.self::limit($content, 6).' featured projects']),
            PageSectionType::BlogPosts => self::join([$heading, 'Latest '.self::limit($content, 3).' posts']),
            PageSectionType::Services => self::join([
                $heading,
                self::count(count(self::list($content['service_slugs'] ?? null)), 'selected service') ?? 'Homepage services',
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private static function heading(array $content): ?string
    {
        $heading = self::join([
            self::text($content['heading'] ?? null),
            self::text($content['heading_highlight'] ?? null),
        ], ' ');

        return $heading ?? self::text($content['eyebrow'] ?? null);
    }

    /**
     * Gallery items were a flat content_json.media_ids list before WEB-101 —
     * count whichever shape the section actually holds.
     *
     * @param  array<string, mixed>  $content
     */
    private static function galleryCount(array $content): ?string
    {
        $items = self::list($content['gallery_items'] ?? null);

        if ($items === []) {
            return self::count(count(self::list($content['media_ids'] ?? null)), 'image');
        }

        $allImages = collect($items)->every(fn ($item): bool => filled($item['media_id'] ?? null));

        return self::count(count($items), $allImages ? 'image' : 'item');
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private static function limit(array $content, int $default): int
    {
        return is_numeric($content['limit'] ?? null) ? (int) $content['limit'] : $default;
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_filter($value, fn ($item): bool => filled($item)) : [];
    }

    private static function count(int $count, string $noun): ?string
    {
        return $count > 0 ? $count.' '.Str::plural($noun, $count) : null;
    }

    /**
     * Plain text from a textarea or RichEditor value: tags stripped, block
     * boundaries and line breaks collapsed to single spaces.
     */
    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = preg_replace('/<(br|\/p|\/h[1-6]|\/li|\/div)[^>]*>/i', ' ', $value) ?? $value;
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, string|null>  $parts
     */
    private static function join(array $parts, string $glue = ' · '): ?string
    {
        $parts = array_values(array_filter($parts, fn (?string $part): bool => filled($part)));

        return $parts !== [] ? implode($glue, $parts) : null;
    }
}
