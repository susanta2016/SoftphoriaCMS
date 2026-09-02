<?php

namespace App\Shared\Support\Search;

/**
 * Implemented by any Eloquent model that unified Search (App\Modules\Search)
 * can surface as a result — gives SearchService a model-agnostic way to
 * build a normalized SearchResult DTO without knowing which concrete
 * content type it's dealing with. Mirrors App\Shared\Support\Reviews\
 * Reviewable's shape/purpose exactly, one level richer (an image is
 * optional, everything else is required).
 */
interface SearchResultRepresentable
{
    public function searchResultType(): string;

    public function searchResultTitle(): string;

    public function searchResultExcerpt(): string;

    public function searchResultImageUrl(): ?string;

    public function searchResultUrl(): string;
}
