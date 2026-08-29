<?php

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Account\PasswordController as AccountPasswordController;
use App\Http\Controllers\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\Account\SubscriptionController as AccountSubscriptionController;
use App\Http\Controllers\Account\TransactionController as AccountTransactionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InspirationalResources\InspirationalResourceSubmissionController;
use App\Http\Controllers\Media\PublicHeroVideoStreamController;
use App\Http\Controllers\Media\StreamMediaController;
use App\Http\Controllers\Music\CartController;
use App\Http\Controllers\Music\CheckoutController;
use App\Http\Controllers\Music\GuestDownloadController;
use App\Http\Controllers\Music\MusicController;
use App\Http\Controllers\Music\TrackDownloadController;
use App\Http\Controllers\Music\TrackStreamController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Page\PreviewPageController;
use App\Http\Controllers\PoetryProse\PoetryProseController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\EnsureAccountIsUsable;
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

// Login/logout + password reset. AppServiceProvider::routeResetPasswordThroughEmailTemplates()
// already expects a route named "password.reset" accepting token+email — this
// is that route. Guest-only endpoints are throttled the same as register.free.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Authenticated account area. EnsureAccountIsUsable re-checks the user's
// status on every request (an admin suspending/banning them mid-session
// isn't caught by `auth` alone). No route here ever takes a user id/slug —
// every action operates on Auth::user() only.
Route::middleware(['auth', EnsureAccountIsUsable::class])->prefix('account')->name('account.')->group(function () {
    // Bare /account is named in the spec but never a distinct page — it
    // just lands wherever a signed-in member actually wants to be.
    Route::redirect('/', '/account/dashboard')->name('index');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [AccountProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [AccountProfileController::class, 'update'])->name('profile.update');

    Route::get('/password', [AccountPasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [AccountPasswordController::class, 'update'])->name('password.update');

    Route::get('/subscription', AccountSubscriptionController::class)->name('subscription');
    Route::get('/transactions', AccountTransactionController::class)->name('transactions');

    // Phase 4: the registered purchaser's digital purchase/download library —
    // distinct from /account/transactions (the payment/subscription ledger
    // above, unchanged). Scoped entirely through Auth::user()->orders(); see
    // OrderController's own docblock.
    Route::get('/orders', AccountOrderController::class)->name('orders');
});

// Public Poetry/Prose — fully public once Published (client-confirmed: no
// membership/entitlement gate on viewing in this module).
Route::get('/poetry-prose', [PoetryProseController::class, 'index'])->name('poetry-prose.index');
Route::get('/poetry-prose/{poetryProse:slug}', [PoetryProseController::class, 'show'])->name('poetry-prose.show');

// Public Inspirational Resources — a single introductory/submission page
// only (client-confirmed, final). No public listing, no per-submission
// detail page, no separate public editorial model — a submission is always
// a private administrative record, never rendered at a public URL. The
// submit endpoint is throttled the same as register.free/newsletter.subscribe.
Route::get('/inspirational-resources', [InspirationalResourceSubmissionController::class, 'index'])->name('inspirational-resources.index');
Route::post('/inspirational-resources/submit', [InspirationalResourceSubmissionController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('inspirational-resources.submit');

// Public Music — landing/catalogue + Album/Single listening pages. Fully
// public once Published, same shape as Poetry/Prose above. The stream route
// is playback-only (no entitlement/download-count check — see
// TrackStreamController's docblock); the download route below is the real
// Commerce-backed authorization path (active Subscription or a paid
// Entitlement), auth-only.
Route::get('/music', [MusicController::class, 'index'])->name('music.index');
Route::get('/music/albums/{album:slug}', [MusicController::class, 'showAlbum'])->name('music.albums.show');
Route::get('/music/singles/{single:slug}', [MusicController::class, 'showSingle'])->name('music.singles.show');
Route::get('/music/tracks/{track:slug}', [MusicController::class, 'showTrack'])->name('music.tracks.show');
Route::get('/music/tracks/{track:slug}/stream', TrackStreamController::class)->name('music.tracks.stream');
Route::get('/music/tracks/{track:slug}/download', TrackDownloadController::class)
    ->middleware('auth')
    ->name('music.tracks.download');

// Digital-only cart/checkout for Music purchases (Single/Album — see
// CartSession's docblock for why the cart itself is session-only, never a
// DB row, until checkout). No shipping anywhere in this flow — there's
// nothing to fulfil beyond payment + entitlement issuance (handled by the
// existing Stripe webhook, unchanged here). Guest and registered purchasers
// both go through the same routes; only the guest info step differs.
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'process'])
    ->middleware('throttle:6,1')
    ->name('checkout.process');
Route::get('/checkout/return/{order:public_id}', [CheckoutController::class, 'returnPage'])->name('checkout.return');

// Phase 4: guest download access, reached only via the one-per-order link
// SendGuestDownloadAccessEmailAction sends once an Order is paid. Two
// independent gates (emailed token possession + purchase-email knowledge) —
// see GuestDownloadController's own docblock — with every actual download
// still going through the existing, unmodified AuthorizeTrackDownloadAction.
Route::get('/downloads/guest/{order:public_id}', [GuestDownloadController::class, 'show'])->name('downloads.guest.show');
Route::post('/downloads/guest/{order:public_id}/verify', [GuestDownloadController::class, 'verify'])
    ->middleware('throttle:6,1')
    ->name('downloads.guest.verify');
Route::get('/downloads/guest/{order:public_id}/items', [GuestDownloadController::class, 'items'])->name('downloads.guest.items');
// withoutScopedBindings(): Order has no tracks() relation for Laravel's
// automatic parent-scoped implicit binding to call (a guest order's
// entitlements can cover a whole Album, not a fixed track list) — the
// controller itself already re-derives which entitlement/token covers this
// exact track for this exact order, so route-level scoping would be
// redundant even if it existed.
Route::get('/downloads/guest/{order:public_id}/tracks/{track:slug}', [GuestDownloadController::class, 'download'])
    ->withoutScopedBindings()
    ->name('downloads.guest.track');

// Public CMS page viewer (Stage D) — kept last so it never shadows a more
// specific route above; PageController itself 404s anything not published.
Route::get('/{page:slug}', PageController::class)->name('pages.show');
