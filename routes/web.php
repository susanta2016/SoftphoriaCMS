<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Media\StreamMediaController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Page\PreviewPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// ADMIN-005: admin-only audio/video playback for the Media Library. Auth is
// enforced inside the controller (same canAccessPanel() gate as /admin),
// not by route middleware, since this isn't a Filament panel route.
Route::get('/admin/media/{media}/stream', StreamMediaController::class)
    ->middleware('web')
    ->name('media.stream');

// ADMIN-006 review fix: admin-only Page preview, opened in a new tab from
// the edit form's Preview action. Same reasoning as media.stream above.
Route::get('/admin/pages/{page}/preview', PreviewPageController::class)
    ->middleware('web')
    ->name('pages.preview');

// Public CMS page viewer (Stage D) — kept last so it never shadows a more
// specific route above; PageController itself 404s anything not published.
Route::get('/{page:slug}', PageController::class)->name('pages.show');
