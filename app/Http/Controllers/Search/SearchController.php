<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\Search\Services\SearchService;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Public unified search — full results page (index, paginated, async
 * region-swap like every other public list page — see resources/js/app.js's
 * data-search-region block) and a separate, small autocomplete endpoint
 * (suggest, JSON) attached to the header's own expanding search control.
 * Both read from one shared SearchService so query handling/normalization
 * never has to be kept in sync across two implementations.
 *
 * Never indexed by search engines itself (ROBOTS_NOINDEX below) — an
 * internal query-driven results page is not meant to become its own SEO
 * surface, same reasoning as every other private/transactional page that
 * passes this constant.
 */
class SearchController extends Controller
{
    public function index(Request $request, SearchService $service, SettingsRepository $settings): View
    {
        $query = SearchService::normalizeQuery($request->string('q')->value());
        $page = max(1, (int) $request->integer('page', 1));

        $results = $service->paginate($query, perPage: 12, page: $page, path: route('search.index'));

        if ($request->ajax()) {
            return view('search.partials.results', [
                'query' => $query,
                'results' => $results,
            ]);
        }

        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => $query !== '' ? "Search: {$query} — {$chrome['siteName']}" : "Search — {$chrome['siteName']}",
            'description' => 'Search across Music, Poetry/Prose, Inspirational Resources, and Community.',
            'canonical' => route('search.index'),
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
            'type' => 'website',
        ], $chrome['general']);

        return view('search.index', [
            ...$chrome,
            'seo' => $seo,
            'query' => $query,
            'results' => $results,
        ]);
    }

    /**
     * Small JSON payload for the header's autocomplete dropdown — a query
     * shorter than SearchService::MIN_LENGTH (validated both here and,
     * redundantly, client-side before the request even fires) simply
     * returns an empty suggestion list rather than a validation error: this
     * endpoint is polled on near-every keystroke, so a noisy 422 for
     * "still typing" is the wrong shape of response for its caller.
     */
    public function suggest(Request $request, SearchService $service): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => ['nullable', 'string', 'max:'.SearchService::MAX_LENGTH],
        ]);

        if ($validator->fails()) {
            return response()->json(['suggestions' => [], 'query' => '']);
        }

        $query = SearchService::normalizeQuery($request->string('q')->value());
        $suggestions = $service->suggest($query, limit: 6);

        return response()->json([
            'query' => $query,
            'suggestions' => $suggestions->map(fn ($result) => [
                'type' => $result->type,
                'title' => $result->title,
                'image' => $result->image,
                'url' => $result->url,
            ])->all(),
            'viewAllUrl' => $query !== '' ? route('search.index', ['q' => $query]) : route('search.index'),
        ]);
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
