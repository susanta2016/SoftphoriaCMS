<?php

namespace App\Shared\Support\Pages;

use App\Enums\PageSectionType;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Eloquent\Collection;

/**
 * Homepage sections that other public pages show again — e.g. the Services
 * pages reuse the homepage's "Why Softphoria" and "Our Process" blocks
 * rather than keeping a second, hardcoded copy. Whatever an admin edits in
 * Pages → Home is what every page shows; a section that's disabled (or a
 * home page that isn't published) simply isn't returned.
 *
 * Render the result with x-site.sections, which already knows each block.
 */
class HomeSections
{
    /** Gallery "Intro + features" — the "Why Softphoria" block. */
    public const WHY = 'why';

    /** Gallery "Numbered steps" — the "Our Process" block. */
    public const PROCESS = 'process';

    /**
     * The requested sections, in the order asked for.
     *
     * @param  array<int, string>  $keys  self::WHY / self::PROCESS
     * @return Collection<int, PageSection>
     */
    public static function get(array $keys): Collection
    {
        $sections = Page::query()
            ->published()
            ->where('slug', 'home')
            ->first()
            ?->sections()
            ->where('is_enabled', true)
            ->where('section_type', PageSectionType::Gallery->value)
            ->orderBy('sort_order')
            ->get() ?? new Collection;

        $displays = [self::WHY => 'features', self::PROCESS => 'steps'];

        return new Collection(array_values(array_filter(array_map(
            fn (string $key): ?PageSection => $sections->first(fn (PageSection $section): bool => ($section->content_json['display'] ?? null) === ($displays[$key] ?? null)),
            $keys,
        ))));
    }
}
