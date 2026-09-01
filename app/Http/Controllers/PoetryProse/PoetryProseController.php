<?php

namespace App\Http\Controllers\PoetryProse;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Public Poetry/Prose list + detail — fully public once Published
 * (client-confirmed: no membership/entitlement gate on viewing in this
 * module). Mirrors PodcastController::episodes()'s shape for the listing
 * page's search/filter/sort/view-toggle + sidebar UX, AJAX partial-swap
 * included (see resources/js/app.js's data-poetry-prose-results-region
 * block) — a plain GET fallback still works with JS disabled since every
 * control is a real form field or link.
 */
class PoetryProseController extends Controller
{
    public function index(Request $request, SettingsRepository $settings): View
    {
        $search = trim((string) $request->string('q'));
        $category = trim((string) $request->string('category'));
        $collection = trim((string) $request->string('collection'));
        $contentType = $request->string('content_type')->value();
        $sort = $request->string('sort')->value() === 'oldest' ? 'oldest' : 'newest';
        $view = $request->string('view')->value() === 'grid' ? 'grid' : 'list';

        $entries = PoetryProse::query()
            ->published()
            ->with(['featuredImage', 'categories'])
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->when($contentType !== '', fn (Builder $query) => $query->where('content_type', $contentType))
            ->when($category !== '', fn (Builder $query) => $query->whereHas('categories', fn ($q) => $q->where('categories.slug', $category)))
            ->when($collection !== '', fn (Builder $query) => $query->whereHas('collection', fn ($q) => $q->where('slug', $collection)))
            ->orderBy('publish_at', $sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy('id', $sort === 'oldest' ? 'asc' : 'desc')
            ->paginate(6)
            ->withQueryString();

        $filters = compact('search', 'category', 'collection', 'contentType', 'sort', 'view');
        $categories = $this->categoryCounts();
        $totalPublished = PoetryProse::query()->published()->count();
        $popular = $this->popularEntries();
        $copy = $this->landingCopy($settings);

        // The filter form, sort, view toggle, category links, and
        // pagination all submit here asynchronously (see the
        // data-poetry-prose-results-region/-filters-form wiring in
        // resources/js/app.js), same pattern as
        // PodcastController::episodes(). The whole toolbar+results+sidebar
        // grid is swapped together — not just the results column — so the
        // sidebar's active-category highlighting, counts, and "Clear
        // filters" link all stay in sync with the applied filters.
        if ($request->ajax()) {
            return view('poetry-prose.partials.results', [
                'entries' => $entries,
                'filters' => $filters,
                'contentTypes' => PoetryProseContentType::options(),
                'categories' => $categories,
                'totalPublished' => $totalPublished,
                'popular' => $popular,
                'aboutBody' => $copy['aboutBody'],
                'submitCtaLabel' => $copy['submitCtaLabel'],
            ]);
        }

        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Poetry/Prose — {$chrome['siteName']}",
            'description' => $copy['heroDescription'],
            'canonical' => route('poetry-prose.index'),
            'type' => 'website',
        ], $chrome['general']);

        return view('poetry-prose.index', [
            ...$chrome,
            ...$copy,
            'seo' => $seo,
            'entries' => $entries,
            'filters' => $filters,
            'contentTypes' => PoetryProseContentType::options(),
            'categories' => $categories,
            'totalPublished' => $totalPublished,
            'popular' => $popular,
            'heroBanner' => $this->heroBanner($settings),
        ]);
    }

    public function show(PoetryProse $poetryProse, SettingsRepository $settings): View
    {
        abort_unless($poetryProse->status === PoetryProseStatus::Published, 404);

        $chrome = $this->siteChrome($settings);
        $copy = $this->landingCopy($settings);
        $poetryProse->load(['featuredImage', 'author', 'categories', 'tags', 'collection']);

        $previous = PoetryProse::query()
            ->published()
            ->where(fn (Builder $q) => $q
                ->where('publish_at', '<', $poetryProse->publish_at)
                ->orWhere(fn ($qq) => $qq->where('publish_at', $poetryProse->publish_at)->where('id', '<', $poetryProse->id)))
            ->orderByDesc('publish_at')
            ->orderByDesc('id')
            ->first();

        $next = PoetryProse::query()
            ->published()
            ->where(fn (Builder $q) => $q
                ->where('publish_at', '>', $poetryProse->publish_at)
                ->orWhere(fn ($qq) => $qq->where('publish_at', $poetryProse->publish_at)->where('id', '>', $poetryProse->id)))
            ->orderBy('publish_at')
            ->orderBy('id')
            ->first();

        $seo = SeoTagBuilder::build($poetryProse->seo, [
            'title' => "{$poetryProse->title} — {$chrome['siteName']}",
            'description' => $poetryProse->excerpt(),
            'canonical' => route('poetry-prose.show', $poetryProse),
            'type' => 'article',
            'image' => $poetryProse->featuredImage,
            'published_at' => $poetryProse->publish_at,
            'modified_at' => $poetryProse->updated_at,
            'author_name' => $poetryProse->author?->name,
        ], $chrome['general']);

        return view('poetry-prose.show', [
            ...$chrome,
            ...$copy,
            'seo' => $seo,
            'entry' => $poetryProse,
            'previous' => $previous,
            'next' => $next,
            'categories' => $this->categoryCounts(),
            'totalPublished' => PoetryProse::query()->published()->count(),
            'popular' => $this->popularEntries($poetryProse->id),
            'heroBanner' => $this->heroBanner($settings),
        ]);
    }

    private function heroBanner(SettingsRepository $settings): ?Media
    {
        $mediaId = $settings->get('poetry_prose', 'hero_banner_media_id');

        return $mediaId ? Media::find($mediaId) : null;
    }

    /**
     * @return array{heroEyebrow: string, heroHeading: string, heroDescription: string, aboutBody: string, submitCtaLabel: string}
     */
    private function landingCopy(SettingsRepository $settings): array
    {
        return [
            'heroEyebrow' => $settings->get('poetry_prose', 'hero_eyebrow', 'Poetry / Prose'),
            'heroHeading' => $settings->get('poetry_prose', 'hero_heading', 'Words that awaken and inspire.'),
            'heroDescription' => $settings->get(
                'poetry_prose',
                'hero_description',
                'Explore reflections, poems, and prose that hold space for thought, feeling, and the light within.',
            ),
            'aboutBody' => $settings->get(
                'poetry_prose',
                'about_body',
                "Here you'll find words to reflect on, return to, and carry with you.\n\nPoems, reflections, and essays that speak to the heart and awaken the soul.",
            ),
            'submitCtaLabel' => $settings->get('poetry_prose', 'submit_cta_label', 'Submit Your Writing'),
        ];
    }

    /**
     * Category has no inverse "poetryProseEntries" relation (it's a generic,
     * cross-module model keyed only by `type`) — counted via the
     * poetry_prose_categories pivot directly, mirroring
     * PodcastController::topicCounts() exactly.
     *
     * @return Collection<int, array{category: Category, count: int}>
     */
    private function categoryCounts(): Collection
    {
        return Category::query()
            ->where('type', 'poetry_prose')
            ->get()
            ->map(fn (Category $category): array => [
                'category' => $category,
                'count' => PoetryProse::query()
                    ->published()
                    ->whereHas('categories', fn ($query) => $query->where('categories.id', $category->id))
                    ->count(),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->sortByDesc('count')
            ->values();
    }

    /**
     * No popularity/view-count tracking exists anywhere in the app for this
     * module — falls back to most-recently-published, the same recency
     * signal PodcastController's own "Latest Episodes" sidebar block uses.
     */
    private function popularEntries(?int $excludeId = null): Collection
    {
        return PoetryProse::query()
            ->published()
            ->when($excludeId, fn (Builder $query) => $query->where('id', '!=', $excludeId))
            ->with('featuredImage')
            ->orderByDesc('publish_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();
    }

    /**
     * @return array{siteName: string, tagline: ?string, logo: ?Media, general: array<string, mixed>}
     */
    private function siteChrome(SettingsRepository $settings): array
    {
        $general = $settings->all('general');
        $logoMediaId = $general['logo_media_id'] ?? null;

        return [
            'siteName' => ($general['site_name'] ?? null) ?: config('app.name'),
            'tagline' => $general['tagline'] ?? null,
            'logo' => $logoMediaId ? Media::find($logoMediaId) : null,
            'general' => $general,
        ];
    }
}
