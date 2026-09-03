<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Media\PublicHeroVideoStreamController;
use App\Http\Controllers\Media\StreamMediaController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Page\PreviewPageController;
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

// ADMIN-010: public Contact Us page + submission. Spam protection is a
// honeypot field (silently discarded in the controller) plus rate limiting
// on the POST route — see ContactController's own docblock.
Route::get('/contact', [ContactController::class, 'index'])->name('contact.index');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('contact.submit');

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

// Public CMS page viewer (Stage D) — kept last so it never shadows a more
// specific route above; PageController itself 404s anything not published.
Route::get('/{page:slug}', PageController::class)->name('pages.show');
