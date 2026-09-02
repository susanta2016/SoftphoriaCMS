<?php

namespace App\Modules\Search\Services;

use App\Models\LightPost;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Modules\Search\DTOs\SearchResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Unified site-wide search — reads Laravel Scout ("database" driver,
 * config('scout.driver')) across the 7 approved public content models
 * (see App\Shared\Support\Search\SearchResultRepresentable), normalizes
 * every hit into a SearchResult DTO, and serves both the full results page
 * (paginated) and the header autocomplete (a small capped slice of the same
 * merged, sorted result set) from one shared code path — never two separate
 * query implementations to keep in sync.
 *
 * Each model's own newScoutQuery() override (Album/Single/Track/PoetryProse/
 * PodcastEpisode/ResourceSubmission/LightPost) is the actual publication-
 * visibility gate — see those methods' docblocks for why that, not
 * shouldBeSearchable(), is what matters for Scout's "database" driver. This
 * service adds no visibility logic of its own; it only shapes/sorts/paginates
 * what those per-model scoped queries already return.
 */
class SearchService
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 100;

    /**
     * A live LIKE query per model (no separate search index — see
     * DatabaseEngine) — capped generously per model so a broad, common term
     * can't turn into an unbounded table scan across 7 tables. Phase 1
     * content volumes are small; revisit if the catalogue grows enough for
     * this to matter.
     */
    private const PER_MODEL_LIMIT = 100;

    /**
     * @var array<int, class-string>
     */
    private const MODELS = [
        Album::class,
        Single::class,
        Track::class,
        PoetryProse::class,
        PodcastEpisode::class,
        ResourceSubmission::class,
        LightPost::class,
    ];

    /**
     * Trim + collapse internal whitespace, same convention as
     * PoetryProse::plainTextBody()'s ->squish() call — applied before any
     * length check or query so " light   within " and "light within" behave
     * identically.
     */
    public static function normalizeQuery(?string $query): string
    {
        return Str::squish((string) $query);
    }

    /**
     * @return Collection<int, SearchResult>
     */
    public function results(string $query): Collection
    {
        $query = self::normalizeQuery($query);

        if ($query === '' || mb_strlen($query) < self::MIN_LENGTH) {
            return collect();
        }

        $query = mb_substr($query, 0, self::MAX_LENGTH);

        // Scout's database engine (Laravel\Scout\Engines\DatabaseEngine)
        // wraps the raw query string in %...% for a LIKE match without
        // escaping literal "%"/"_" the visitor typed — left unescaped, those
        // are interpreted as SQL wildcards instead of the literal characters
        // a visitor meant (e.g. a lone "%" would match every row of every
        // model). Escaping here keeps the search literal and predictable;
        // this is a correctness fix, not an injection risk — Laravel already
        // parameterizes the bound value either way.
        $escaped = addcslashes($query, '\\%_');

        return collect(self::MODELS)
            ->flatMap(fn (string $model): Collection => $this->searchModel($model, $escaped))
            ->map(fn ($model): SearchResult => SearchResult::fromModel($model))
            ->sortByDesc(fn (SearchResult $result) => $result->sortDate)
            ->values();
    }

    /**
     * @return Collection<int, SearchResult>
     */
    public function suggest(string $query, int $limit = 6): Collection
    {
        return $this->results($query)->take($limit)->values();
    }

    public function paginate(string $query, int $perPage, int $page, string $path): LengthAwarePaginatorContract
    {
        $page = max(1, $page);
        $all = $this->results($query);

        return new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $path, 'pageName' => 'page'],
        );
    }

    /**
     * @param  class-string  $model
     * @return Collection<int, Model>
     */
    private function searchModel(string $model, string $query): Collection
    {
        // A light, model-specific eager load for whichever relation each
        // model's own searchResultImageUrl()/searchResultTitle() touches —
        // avoids an N+1 query per result row once results are merged, never
        // changes what's returned.
        $with = match ($model) {
            Album::class, Single::class => ['cover'],
            Track::class => ['album.cover'],
            PoetryProse::class => ['featuredImage'],
            PodcastEpisode::class => ['artwork'],
            LightPost::class => ['user'],
            default => [],
        };

        return $model::search($query)
            ->when($with !== [], fn ($builder) => $builder->query(fn ($q) => $q->with($with)))
            ->take(self::PER_MODEL_LIMIT)
            ->get();
    }
}
