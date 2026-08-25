<?php

use App\Modules\Commerce\Providers\CommerceServiceProvider;
use App\Modules\InspirationalResources\Providers\InspirationalResourcesServiceProvider;
use App\Modules\Music\Providers\MusicServiceProvider;
use App\Modules\Podcast\Providers\PodcastServiceProvider;
use App\Modules\PoetryProse\Providers\PoetryProseServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled Softphoria Modules
    |--------------------------------------------------------------------------
    |
    | Each entry is the fully-qualified class name of a module's provider,
    | which must extend App\Shared\Support\Modules\ModuleServiceProvider.
    | The Softphoria ModuleRegistry (see AppServiceProvider) registers every
    | class listed here with the application container during boot.
    |
    | Removing an entry disables that module without touching application
    | bootstrap code. No modules are registered yet — the Softphoria Core
    | ships with no business modules; each future module (Homepage, About,
    | Music, Podcast, PoetryProse, InspirationalResources, Community, Users,
    | Newsletter, Search, Media, SEO, Analytics, Settings, Security) adds
    | itself here when it is implemented.
    |
    | Example:
    |     \App\Modules\Music\Providers\MusicServiceProvider::class,
    |
    */

    'enabled' => [
        PodcastServiceProvider::class,
        MusicServiceProvider::class,
        CommerceServiceProvider::class,
        PoetryProseServiceProvider::class,
        InspirationalResourcesServiceProvider::class,
    ],

];
