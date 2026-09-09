<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary Beta Access Gate
    |--------------------------------------------------------------------------
    |
    | Password-only "coming soon" gate for the whole public site (App\Http\
    | Middleware\BetaAccessGate), independent of and never a replacement for
    | the real Laravel membership/authentication system. Both values are
    | env-only — never set in this file, never committed — so enabling the
    | gate, disabling it, or changing the password is a production .env edit
    | plus a container restart, with no application source file to touch.
    |
    | 'enabled' is the master switch. 'password' is deliberately allowed to
    | be blank even when enabled: BetaAccessController treats a blank
    | configured password as "nobody can pass" rather than accidentally
    | matching a blank submission.
    |
    */

    'enabled' => (bool) env('BETA_ACCESS_ENABLED', false),

    'password' => env('BETA_ACCESS_PASSWORD'),

];
