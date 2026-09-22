<?php

namespace App\Modules\Search\DTOs;

use App\Shared\Support\Search\SearchResultRepresentable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The normalized shape every unified-Search result is reduced to
 * (title/type/excerpt/image/url — the exact structure the client's
 * requirement specifies), regardless of which of the 7 underlying content
 * models it came from. Built from a model's own
 * App\Shared\Support\Search\SearchResultRepresentable methods — never
 * duplicates content, only reads it.
 */
final class SearchResult
{
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $excerpt,
        public readonly ?string $image,
        public readonly string $url,
        public readonly Carbon $sortDate,
        public readonly bool $titleMatch,
    ) {}

    /**
     * @param  string  $query  The normalized (trimmed/squished) search term
     *                         — used only to rank a title match above a
     *                         match that only occurred in body/description
     *                         text (see SearchService::results()'s sort).
     *                         Every model's own toSearchableArray() already
     *                         decided this row belongs in the result set at
     *                         all; this never filters anything out.
     */
    public static function fromModel(Model&SearchResultRepresentable $model, string $query): self
    {
        $title = $model->searchResultTitle();

        return new self(
            type: $model->searchResultType(),
            title: $title,
            excerpt: $model->searchResultExcerpt(),
            image: $model->searchResultImageUrl(),
            url: $model->searchResultUrl(),
            sortDate: $model->updated_at ?? Carbon::now(),
            titleMatch: $query !== '' && Str::contains($title, $query, ignoreCase: true),
        );
    }
}
