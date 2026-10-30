<?php

use App\Modules\Newsletter\Http\Controllers\TrackingController;
use App\Modules\Newsletter\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

/*
| Links inside campaign emails. Registered WITHOUT the "web" middleware
| group on purpose: they need no session, cookies or CSRF (the random
| recipient token is the authorization), and must keep working while the
| site is behind the beta gate or in maintenance mode — both are "web"
| group middleware. Throttled per IP.
|
| Not gated by NEWSLETTER_MARKETING_ENABLED: unsubscribing and existing
| links must work whatever the admin-side switch says (see
| config/newsletter.php). Tracking records nothing while the module is off.
*/

Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/newsletter/unsubscribe/{token}', [UnsubscribeController::class, 'show'])
        ->where('token', '[A-Za-z0-9]+')
        ->name('newsletter.unsubscribe');

    Route::post('/newsletter/unsubscribe/{token}', [UnsubscribeController::class, 'store'])
        ->where('token', '[A-Za-z0-9]+')
        ->name('newsletter.unsubscribe.confirm');
});

Route::middleware('throttle:120,1')->group(function (): void {
    Route::get('/n/o/{token}', [TrackingController::class, 'open'])
        ->where('token', '[A-Za-z0-9]+')
        ->name('newsletter.track.open');

    Route::get('/n/c/{token}/{link}', [TrackingController::class, 'click'])
        ->where(['token' => '[A-Za-z0-9]+', 'link' => '[a-f0-9]{16}'])
        ->name('newsletter.track.click');
});
