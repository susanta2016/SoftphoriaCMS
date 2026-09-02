<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Music\MusicController;
use App\Http\Controllers\Podcast\PodcastController;
use App\Models\Page;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Modules\PoetryProse\Models\PoetryProse;

return [

    /*
    |--------------------------------------------------------------------
    | Sitemap sources
    |--------------------------------------------------------------------
    |
    | Every class contributing URLs to /sitemap.xml — each must implement
    | App\Shared\Support\Seo\Sitemapable. SitemapController itself never
    | changes: it just loops over this list and calls each source's
    | sitemapEntries(). Add a future PUBLIC content type's model here (and
    | nowhere else) once it implements the interface — Music/Album/Single,
    | PodcastEpisode, Poetry/Prose, etc. (docs/development instructions
    | for SEO.docx §1/§6/§9: never hard-code the sitemap to today's
    | content types).
    |
    | Never add a private/member-only type here — an Account/Profile page,
    | a Gratitude Journal entry, a Light Post, or premium/entitlement-gated
    | content must NOT be registered just because it has a database row and
    | a URL. See Sitemapable's docblock for the full public-vs-private rule
    | this list depends on.
    |
    | Inspirational Resources (REVERSED 2026-09-02): an Approved
    | ResourceSubmission is now genuinely public — its own listing/detail
    | page, mirroring Poetry/Prose — so it's registered below like any
    | other public content type. Everything before Approved (Submitted/
    | InReview) and Archived stays a private administrative record and is
    | excluded by ResourceSubmission::sitemapEntries()'s own approved scope.
    |
    */

    'sitemap_sources' => [
        HomeController::class,
        Page::class,
        PoetryProse::class,
        ResourceSubmission::class,
        MusicController::class,
        Album::class,
        Single::class,
        Track::class,
        PodcastController::class,
        PodcastEpisode::class,
    ],

];
