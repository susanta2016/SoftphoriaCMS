<?php

namespace App\Shared\Support\Reviews;

/**
 * Implemented by any Eloquent model that can carry the generic App\Models\
 * Review polymorphic relation (PodcastEpisode today; Track/InspirationalResource
 * later) — gives App\Actions\Review\PublishReviewAction a model-agnostic way
 * to build the "review_published" email's variables without knowing which
 * concrete type it's dealing with.
 */
interface Reviewable
{
    public function reviewTitle(): string;

    public function reviewUrl(): string;
}
