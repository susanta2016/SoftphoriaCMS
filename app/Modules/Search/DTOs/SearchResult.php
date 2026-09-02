<?php

namespace App\Modules\Search\DTOs;

use App\Shared\Support\Search\SearchResultRepresentable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
    ) {}

    public static function fromModel(Model&SearchResultRepresentable $model): self
    {
        return new self(
            type: $model->searchResultType(),
            title: $model->searchResultTitle(),
            excerpt: $model->searchResultExcerpt(),
            image: $model->searchResultImageUrl(),
            url: $model->searchResultUrl(),
            sortDate: $model->updated_at ?? Carbon::now(),
        );
    }
}
