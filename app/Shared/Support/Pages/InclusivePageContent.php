<?php

namespace App\Shared\Support\Pages;

use App\Enums\ModuleKey;
use App\Enums\PageSectionType;
use App\Models\Page;
use App\Models\PageSection;
use App\Modules\Music\Support\ReleaseCatalogue;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Modules\PoetryProse\Models\PoetryProse;
use Illuminate\Support\Collection;

/**
 * Builds the Inclusive page's content sections (PageTemplate::Inclusive).
 *
 * Each enabled Featured Content section whose Module is Music, Poetry/Prose
 * or Podcast becomes one block showing that module's latest published
 * records, in the page's own section order. Nothing is copied: only the
 * section's display settings live in page_sections.content_json; the
 * records are always read live through each module's own published()
 * scope, so a draft/archived/scheduled record (or a Podcast episode whose
 * parent show is unpublished) can never appear here even briefly.
 *
 * Every item links to its existing public detail page — no players, cart
 * or download actions are rendered here, so all existing guest/registered
 * access rules stay enforced exactly where they already are.
 */
class InclusivePageContent
{
    public const DEFAULT_ITEMS = 6;

    public const MIN_ITEMS = 3;

    public const MAX_ITEMS = 12;

    /**
     * The modules an Inclusive section can aggregate, with their fallback
     * heading / View-all destination when the admin leaves those blank.
     *
     * @return array<string, array{heading: string, view_all_route: string}>
     */
    public static function modules(): array
    {
        return [
            ModuleKey::Music->value => ['heading' => 'Music', 'view_all_route' => 'music.index'],
            ModuleKey::PoetryProse->value => ['heading' => 'Writing', 'view_all_route' => 'poetry-prose.index'],
            ModuleKey::Podcast->value => ['heading' => 'Podcasts', 'view_all_route' => 'podcast.episodes.index'],
        ];
    }

    public static function isAggregatableModule(?string $moduleKey): bool
    {
        return $moduleKey !== null && array_key_exists($moduleKey, self::modules());
    }

    /**
     * The stored "Items to show" value clamped to the supported range;
     * blank/invalid falls back to the default.
     */
    public static function itemLimit(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::DEFAULT_ITEMS;
        }

        return max(self::MIN_ITEMS, min(self::MAX_ITEMS, (int) $value));
    }

    /**
     * @return Collection<int, array{module: string, heading: string, subheading: ?string, view_all_label: string, view_all_url: string, items: Collection<int, mixed>}>
     */
    public function sections(Page $page): Collection
    {
        return $page->sections
            ->filter(fn (PageSection $section): bool => $section->is_enabled
                && $section->section_type === PageSectionType::FeaturedContent->value
                && self::isAggregatableModule($section->content_json['module_key'] ?? null))
            ->sortBy('sort_order')
            ->map(fn (PageSection $section): array => $this->section($section))
            ->filter(fn (array $section): bool => $section['items']->isNotEmpty())
            ->values();
    }

    /**
     * @return array{module: string, heading: string, subheading: ?string, view_all_label: string, view_all_url: string, items: Collection<int, mixed>}
     */
    private function section(PageSection $section): array
    {
        $content = $section->content_json ?? [];
        $module = $content['module_key'];
        $defaults = self::modules()[$module];
        $limit = self::itemLimit($content['items_to_show'] ?? null);

        return [
            'module' => $module,
            'heading' => filled($content['heading'] ?? null) ? $content['heading'] : $defaults['heading'],
            'subheading' => filled($content['subheading'] ?? null) ? $content['subheading'] : null,
            'view_all_label' => filled($content['cta_label'] ?? null) ? $content['cta_label'] : 'View all',
            'view_all_url' => filled($content['cta_url'] ?? null) ? $content['cta_url'] : route($defaults['view_all_route']),
            'items' => $this->items($module, $limit),
        ];
    }

    /**
     * Each module ordered by its own date field, newest first — never a
     * mixed cross-module feed.
     *
     * @return Collection<int, mixed>
     */
    private function items(string $module, int $limit): Collection
    {
        return match ($module) {
            // Albums + Singles only (never individual Tracks), by release_date.
            ModuleKey::Music->value => app(ReleaseCatalogue::class)->latest($limit),

            ModuleKey::PoetryProse->value => PoetryProse::query()
                ->published()
                ->with(['featuredImage', 'categories'])
                ->orderByDesc('publish_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),

            // Same double check as PodcastController: episode AND its
            // parent Podcast must both be Published.
            ModuleKey::Podcast->value => PodcastEpisode::query()
                ->published()
                ->whereHas('podcast', fn ($query) => $query->published())
                ->with(['artwork', 'categories'])
                ->orderByDesc('publish_date')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),

            default => collect(),
        };
    }
}
