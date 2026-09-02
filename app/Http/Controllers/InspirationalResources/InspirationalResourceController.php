<?php

namespace App\Http\Controllers\InspirationalResources;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Public Inspirational Resources — listing + detail, for Approved
 * submissions only (client-confirmed reversal, 2026-09-02: previously
 * there was no public listing/detail at all — see
 * ResourceSubmission's own docblock). Mirrors PoetryProseController's
 * listing/detail shape (search/category/sort/pagination, sidebar), minus
 * the hero banner image (client-confirmed: this landing page doesn't get
 * one) and minus the content-type/collection filters PoetryProse has,
 * since a submission has neither. `category` here is a free-text column,
 * not a Category-model relation, so the category filter/sidebar work off
 * distinct string values rather than a taxonomy table.
 *
 * The "Submit Your Writing" form itself is a separate page/controller —
 * see InspirationalResourceSubmissionController::create().
 */
class InspirationalResourceController extends Controller
{
    public function index(Request $request, SettingsRepository $settings): View
    {
        $search = trim((string) $request->string('q'));
        $category = trim((string) $request->string('category'));
        $sort = $request->string('sort')->value() === 'oldest' ? 'oldest' : 'newest';
        $view = $request->string('view')->value() === 'grid' ? 'grid' : 'list';

        $submissions = ResourceSubmission::query()
            ->approved()
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->when($category !== '', fn (Builder $query) => $query->where('category', $category))
            ->orderBy('created_at', $sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy('id', $sort === 'oldest' ? 'asc' : 'desc')
            ->paginate(6)
            ->withQueryString();

        $filters = compact('search', 'category', 'sort', 'view');
        $categories = $this->categoryCounts();
        $totalApproved = ResourceSubmission::query()->approved()->count();
        $recent = $this->recentSubmissions();

        // The filter form, sort, view toggle, category links, and
        // pagination all submit here asynchronously (see the
        // data-inspirational-resources-results-region/-filters-form wiring
        // in resources/js/app.js), same pattern as PoetryProseController::
        // index(). The whole toolbar+results+sidebar grid is swapped
        // together — not just the results column — so the sidebar's
        // active-category highlighting, counts, and "Clear filters" link
        // all stay in sync with the applied filters.
        if ($request->ajax()) {
            return view('inspirational-resources.partials.results', [
                'submissions' => $submissions,
                'filters' => $filters,
                'categories' => $categories,
                'totalApproved' => $totalApproved,
                'recent' => $recent,
            ]);
        }

        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Inspirational Resources — {$chrome['siteName']}",
            'description' => 'Stories, testimonies, and reflections shared by our community.',
            'canonical' => route('inspirational-resources.index'),
            'type' => 'website',
        ], $chrome['general']);

        return view('inspirational-resources.index', [
            ...$chrome,
            'seo' => $seo,
            'submissions' => $submissions,
            'filters' => $filters,
            'categories' => $categories,
            'totalApproved' => $totalApproved,
            'recent' => $recent,
        ]);
    }

    public function show(ResourceSubmission $resourceSubmission, SettingsRepository $settings): View
    {
        abort_unless($resourceSubmission->status === ResourceSubmissionStatus::Approved, 404);

        $chrome = $this->siteChrome($settings);

        $previous = ResourceSubmission::query()
            ->approved()
            ->where(fn (Builder $q) => $q
                ->where('created_at', '<', $resourceSubmission->created_at)
                ->orWhere(fn ($qq) => $qq->where('created_at', $resourceSubmission->created_at)->where('id', '<', $resourceSubmission->id)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $next = ResourceSubmission::query()
            ->approved()
            ->where(fn (Builder $q) => $q
                ->where('created_at', '>', $resourceSubmission->created_at)
                ->orWhere(fn ($qq) => $qq->where('created_at', $resourceSubmission->created_at)->where('id', '>', $resourceSubmission->id)))
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        $seo = SeoTagBuilder::build($resourceSubmission->seo, [
            'title' => "{$resourceSubmission->publicTitle()} — {$chrome['siteName']}",
            'description' => $resourceSubmission->excerpt(),
            'canonical' => route('inspirational-resources.show', $resourceSubmission),
            'type' => 'article',
            'published_at' => $resourceSubmission->created_at,
            'modified_at' => $resourceSubmission->updated_at,
        ], $chrome['general']);

        return view('inspirational-resources.show', [
            ...$chrome,
            'seo' => $seo,
            'submission' => $resourceSubmission,
            'previous' => $previous,
            'next' => $next,
            'categories' => $this->categoryCounts(),
            'totalApproved' => ResourceSubmission::query()->approved()->count(),
            'recent' => $this->recentSubmissions($resourceSubmission->id),
        ]);
    }

    /**
     * category is a free-text column (no Category-model relation) —
     * counted via a simple groupBy on the approved scope.
     *
     * @return Collection<int, array{category: string, count: int}>
     */
    private function categoryCounts(): Collection
    {
        return ResourceSubmission::query()
            ->approved()
            ->selectRaw('category, count(*) as aggregate')
            ->groupBy('category')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (ResourceSubmission $row): array => [
                'category' => $row->category,
                'count' => (int) $row->getAttribute('aggregate'),
            ]);
    }

    /**
     * No popularity/view-count tracking exists for submissions — falls back
     * to most-recently-approved, the same recency signal PoetryProse's own
     * "Popular Reads" sidebar block uses.
     */
    private function recentSubmissions(?int $excludeId = null): Collection
    {
        return ResourceSubmission::query()
            ->approved()
            ->when($excludeId, fn (Builder $query) => $query->where('id', '!=', $excludeId))
            ->orderByDesc('created_at')
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
