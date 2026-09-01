<?php

namespace App\Http\Controllers\Podcast;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use App\Shared\Support\Seo\Sitemapable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Public Podcast landing/all-episodes/episode pages — fully public once
 * Published, no membership/entitlement gate on viewing or streaming
 * (Podcast access is independent of the Member Subscription feature flag).
 * Mirrors PoetryProseController/MusicController's thin shape: no auth,
 * published-only, 404 on anything else. Only the download endpoint
 * (PodcastEpisodeDownloadController) is auth-gated.
 */
class PodcastController extends Controller implements Sitemapable
{
    /**
     * Only the landing page — the All Episodes listing is a pure index of
     * the same content PodcastEpisode::sitemapEntries() already covers
     * individually, same reasoning as MusicController not registering its
     * own catalogue listing separately from Album/Single.
     *
     * @return Collection<int, array{loc: string, lastmod: mixed}>
     */
    public static function sitemapEntries(): Collection
    {
        return collect([['loc' => route('podcast.index'), 'lastmod' => now()]]);
    }

    public function index(Request $request, SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);
        $podcast = $this->primaryPodcast();

        $episodes = PodcastEpisode::query()
            ->published()
            ->whereHas('podcast', fn ($query) => $query->published())
            ->with(['artwork', 'categories'])
            ->orderByDesc('publish_date')
            ->orderByDesc('id')
            ->get();

        $featured = $episodes->first();
        $latest = $episodes->slice(1, 4);

        $seo = SeoTagBuilder::build(null, [
            'title' => $podcast ? "{$podcast->title} — {$chrome['siteName']}" : "Podcast — {$chrome['siteName']}",
            'description' => $this->excerpt($podcast?->description) ?? 'Conversations, reflections, and interviews.',
            'canonical' => route('podcast.index'),
            'type' => 'website',
        ], $chrome['general']);

        return view('podcast.index', [
            ...$chrome,
            'seo' => $seo,
            'podcast' => $podcast,
            'featured' => $featured,
            'featuredEmbedUrl' => $featured ? $this->youtubeEmbedUrl($featured) : null,
            'latest' => $latest,
            'topics' => $this->topicCounts(),
            'heroBanner' => $this->heroBanner($settings),
        ]);
    }

    public function episodes(Request $request, SettingsRepository $settings): View
    {
        $search = trim((string) $request->string('q'));
        $topic = trim((string) $request->string('topic'));
        $duration = $request->string('duration')->value();
        $releaseWindow = $request->string('release')->value();
        $sort = $request->string('sort')->value() === 'oldest' ? 'oldest' : 'newest';
        $view = $request->string('view')->value() === 'grid' ? 'grid' : 'list';

        $episodes = PodcastEpisode::query()
            ->published()
            ->whereHas('podcast', fn ($query) => $query->published())
            ->with(['artwork', 'categories', 'tags'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($topic !== '', fn ($query) => $query->whereHas('categories', fn ($q) => $q->where('categories.slug', $topic)))
            ->when($duration === 'short', fn ($query) => $query->where('duration_seconds', '<', 1200))
            ->when($duration === 'medium', fn ($query) => $query->whereBetween('duration_seconds', [1200, 2400]))
            ->when($duration === 'long', fn ($query) => $query->where('duration_seconds', '>', 2400))
            ->when($releaseWindow === '30d', fn ($query) => $query->where('publish_date', '>=', now()->subDays(30)))
            ->when($releaseWindow === '6m', fn ($query) => $query->where('publish_date', '>=', now()->subMonths(6)))
            ->when($releaseWindow === 'year', fn ($query) => $query->where('publish_date', '>=', now()->startOfYear()))
            ->orderBy('publish_date', $sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy('id', $sort === 'oldest' ? 'asc' : 'desc')
            ->paginate(10)
            ->withQueryString();

        $filters = compact('search', 'topic', 'duration', 'releaseWindow', 'sort', 'view');
        $podcast = $this->primaryPodcast();
        $topics = $this->topicCounts();

        // The sort dropdown, list/grid toggle, and the entire results+sidebar
        // grid (including the filter form) all submit here asynchronously
        // (see the data-podcast-episodes-form/-region wiring in
        // resources/js/app.js) — the whole grid is swapped together, not just
        // the results column, so the sidebar's "Clear filters" link and
        // dropdown states stay in sync with the applied filters.
        if ($request->ajax()) {
            return view('podcast.partials.episodes-results', [
                'episodes' => $episodes,
                'filters' => $filters,
                'podcast' => $podcast,
                'topics' => $topics,
            ]);
        }

        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "All Episodes — {$chrome['siteName']}",
            'description' => $this->excerpt($podcast?->description) ?? 'Every episode of the podcast.',
            'canonical' => route('podcast.episodes.index'),
            'type' => 'website',
        ], $chrome['general']);

        return view('podcast.episodes', [
            ...$chrome,
            'seo' => $seo,
            'podcast' => $podcast,
            'episodes' => $episodes,
            'topics' => $topics,
            'filters' => $filters,
            'heroBanner' => $this->heroBanner($settings),
        ]);
    }

    public function show(PodcastEpisode $episode, SettingsRepository $settings): View
    {
        abort_unless($episode->status === PodcastEpisodeStatus::Published, 404);
        abort_unless($episode->podcast?->status === PodcastStatus::Published, 404);

        $chrome = $this->siteChrome($settings);
        $episode->load(['artwork', 'audio', 'podcast', 'categories', 'tags']);

        $reviews = $episode->reviews()->approved()->with('user.profile.avatar')->latest()->get();

        $latest = PodcastEpisode::query()
            ->published()
            ->whereHas('podcast', fn ($query) => $query->published())
            ->where('id', '!=', $episode->id)
            ->with('artwork')
            ->orderByDesc('publish_date')
            ->limit(3)
            ->get();

        $embedUrl = $this->youtubeEmbedUrl($episode);

        $seo = SeoTagBuilder::build($episode->seo, [
            'title' => "{$episode->title} — {$chrome['siteName']}",
            'description' => $this->excerpt($episode->description) ?? "Listen to {$episode->title}.",
            'canonical' => route('podcast.episodes.show', $episode),
            'type' => 'article',
            'image' => $episode->artwork,
            'published_at' => $episode->publish_date,
            'modified_at' => $episode->updated_at,
        ], $chrome['general']);

        return view('podcast.show', [
            ...$chrome,
            'seo' => $seo,
            'episode' => $episode,
            'podcast' => $episode->podcast,
            'latest' => $latest,
            'embedUrl' => $embedUrl,
            'reviews' => $reviews,
            'averageRating' => round((float) $reviews->avg('rating'), 1),
            'reviewCount' => $reviews->count(),
            'heroBanner' => $this->heroBanner($settings),
        ]);
    }

    private function heroBanner(SettingsRepository $settings): ?Media
    {
        $mediaId = $settings->get('podcast', 'hero_banner_media_id');

        return $mediaId ? Media::find($mediaId) : null;
    }

    private function primaryPodcast(): ?Podcast
    {
        return Podcast::query()->published()->orderBy('id')->first();
    }

    /**
     * Category has no inverse "podcastEpisodes" relation (it's a generic,
     * cross-module model keyed only by `type`) — counted here via the
     * podcast_categories pivot directly rather than adding a Podcast-specific
     * relation to a shared model.
     *
     * @return Collection<int, array{category: Category, count: int}>
     */
    private function topicCounts(): Collection
    {
        return Category::query()
            ->where('type', 'podcast')
            ->get()
            ->map(fn (Category $category): array => [
                'category' => $category,
                'count' => PodcastEpisode::query()
                    ->published()
                    ->whereHas('podcast', fn ($query) => $query->published())
                    ->whereHas('categories', fn ($query) => $query->where('categories.id', $category->id))
                    ->count(),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->sortByDesc('count')
            ->values();
    }

    private function youtubeEmbedUrl(PodcastEpisode $episode): ?string
    {
        $videoId = $episode->youtubeVideoId();

        return $videoId ? "https://www.youtube.com/embed/{$videoId}?rel=0" : null;
    }

    private function excerpt(?string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        return str($text)->stripTags()->limit(160)->toString();
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
