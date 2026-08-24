<?php

use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Media\PublicHeroVideoStreamController;
use App\Http\Controllers\Media\StreamMediaController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Page\PreviewPageController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

// Public newsletter signup (footer form) — sends the "newsletter_subscribed"
// Email Template (docs/ARCHITECTURE.md §16.5/§16.6) via TemplatedMailer.
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->middleware('web')
    ->name('newsletter.subscribe');

// ADMIN-005: admin-only audio/video playback for the Media Library. Auth is
// enforced inside the controller (same canAccessPanel() gate as /admin),
// not by route middleware, since this isn't a Filament panel route.
Route::get('/admin/media/{media}/stream', StreamMediaController::class)
    ->middleware('web')
    ->name('media.stream');

// Public playback for a Hero section's "Watch Introduction" video (Range
// requests supported for scrubbing). No auth — gated inside the controller
// on the video being attached to a published Hero section right now.
Route::get('/media/{media}/watch', PublicHeroVideoStreamController::class)
    ->middleware('web')
    ->name('media.watch');

// ADMIN-006 review fix: admin-only Page preview, opened in a new tab from
// the edit form's Preview action. Same reasoning as media.stream above.
Route::get('/admin/pages/{page}/preview', PreviewPageController::class)
    ->middleware('web')
    ->name('pages.preview');

// Public registration (Free + Pro via Stripe Embedded Checkout) — every
// public "Register"/"Sign Up" link on the site points here. Registration
// POSTs are rate-limited to slow credential-stuffing/signup abuse; the
// verification link itself is generous (it's a single-use secret, not a
// guessable form submission) while resend is the strictest, since it's the
// one endpoint that fires an email per request regardless of who's asking.
Route::get('/register', [RegistrationController::class, 'show'])->name('register.show');
Route::post('/register/free', [RegistrationController::class, 'registerFree'])
    ->middleware('throttle:6,1')
    ->name('register.free');
Route::post('/register/pro', [RegistrationController::class, 'registerPro'])
    ->middleware('throttle:6,1')
    ->name('register.pro');
Route::get('/register/free/thank-you', [RegistrationController::class, 'freeThankYou'])->name('register.free.thank-you');

// Landing page for Stripe's embedded-Checkout return_url once payment
// completes — signed (RegisterProUserAction::buildReturnUrl()) so the
// `user` parameter can be trusted without a login system. Confirmation is
// decided purely from our own DB (User::hasActiveMembership(), set only by
// the existing Stripe webhook), never by calling Stripe from here — see
// RegistrationController::proComplete().
Route::get('/register/pro/complete/{user}', [RegistrationController::class, 'proComplete'])->name('register.pro.complete');

Route::get('/verify-email/{token}', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('verification.verify');
Route::post('/register/resend-verification', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:3,1')
    ->name('verification.resend');

// Public CMS page viewer (Stage D) — kept last so it never shadows a more
// specific route above; PageController itself 404s anything not published.
Route::get('/{page:slug}', PageController::class)->name('pages.show');
