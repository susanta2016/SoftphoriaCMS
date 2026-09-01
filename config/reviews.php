<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reviews & Ratings Admin Approval
    |--------------------------------------------------------------------------
    |
    | When true (the default), a newly submitted review/rating is stored as
    | pending and never shown publicly until an administrator approves it
    | (App\Actions\Review\PublishReviewAction) — which also sends the
    | submitter a "review_published" email. When false, a new review is
    | published immediately on submission (PublishReviewAction still runs,
    | so the same email fires). Shared by every module that adopts the
    | generic App\Models\Review — Podcast today, Music/Inspirational
    | Resources later — never module-specific.
    |
    */

    'reviews_ratings_admin_approval' => env('REVIEWS_RATINGS_ADMIN_APPROVAL', true),

];
