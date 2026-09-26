<?php

use App\Actions\Page\ResolvePageRedirectAction;
use App\Http\Controllers\Account\PasswordController as AccountPasswordController;
use App\Http\Controllers\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Blog\BlogCommentController;
use App\Http\Controllers\Blog\BlogController;
use App\Http\Controllers\Blog\BlogReactionController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Media\PublicHeroVideoStreamController;
use App\Http\Controllers\Media\StreamMediaController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Page\PreviewPageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\EnsureAccountIsUsable;
use App\Http\Middleware\EnsureAccountNotBlocked;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

// Public newsletter signup (footer form) — sends the "newsletter_subscribed"
// Email Template (docs/ARCHITECTURE.md §16.5/§16.6) via TemplatedMailer.
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->middleware(['web', 'feature:newsletter'])
    ->name('newsletter.subscribe');

// ADMIN-010: public Contact Us page + submission. Spam protection is a
// honeypot field and a time trap (both silently discarded in the
// controller) plus rate limiting on the POST route — see ContactController's
// own docblock.
Route::get('/contact', [ContactController::class, 'index'])->name('contact.index');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('contact.submit');
// The site's own email/phone/WhatsApp are never in the page source; the
// "Reveal" buttons fetch them here — see ContactController::reveal().
Route::post('/contact/reveal', [ContactController::class, 'reveal'])
    ->middleware('throttle:20,1')
    ->name('contact.reveal');

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

// AUTH-001/AUTH-003: public self-registration + email verification.
// Guest/Registered only — no membership tier, no payment step. Honeypot
// spam protection matches ContactController::store()'s pattern (see
// RegisteredUserController's own docblock).
Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('register.store');
Route::get('/register/thank-you', [RegisteredUserController::class, 'thankYou'])->name('register.thank-you');

Route::get('/verify-email/{token}', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('verification.verify');
Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:3,1')
    ->name('verification.resend');

// AUTH-002/AUTH-004: login/logout + password reset. `guest` on the
// request-side routes matches ContactController's own pattern of never
// forcing state on an already-authenticated visitor; logout is `auth`-only
// and POST-only. AppServiceProvider::routeResetPasswordThroughEmailTemplates()
// already expects a route named "password.reset" accepting token+email —
// this is that route.
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');

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

// AUTH-005: account profile/password management + session security.
// EnsureAccountIsUsable re-checks status on top of `auth` (see its own
// docblock) — PendingVerification is allowed through, only genuinely
// blocked statuses are rejected. Private/member-only content per the
// platform's standing indexing rule: every view here is noindex and never
// Sitemapable, and access is enforced here, not by robots.txt.
Route::middleware(['auth', EnsureAccountIsUsable::class])->prefix('account')->name('account.')->group(function (): void {
    Route::get('/profile', [AccountProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [AccountProfileController::class, 'update'])->name('profile.update');

    Route::get('/password', [AccountPasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [AccountPasswordController::class, 'update'])->name('password.update');
});

// Public Services pages — 404 while Features Activation has "Services
// Pages" off. See ServiceController's docblock for the SEO notes.
Route::prefix('services')->name('services.')->middleware('feature:services')->group(function (): void {
    Route::get('/', [ServiceController::class, 'index'])->name('index');
    Route::get('/{service:slug}', [ServiceController::class, 'show'])->name('show');
});

// Public blog — every route 404s while Admin → Features Activation has
// "Blog Posts" off; archives/comments/reports/reactions each also need their
// own switch. See BlogController's docblock for the SEO/async notes.
Route::prefix('blog')->name('blog.')->middleware('feature:blog.posts')->group(function (): void {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/feed', [BlogController::class, 'feed'])->name('feed');
    Route::get('/category/{category:slug}', [BlogController::class, 'category'])
        ->middleware('feature:blog.categories')
        ->name('category');
    Route::get('/tag/{tag:slug}', [BlogController::class, 'tag'])
        ->middleware('feature:blog.tags')
        ->name('tag');
    Route::get('/{post:slug}/join', [BlogController::class, 'join'])->name('join');
    Route::get('/{post:slug}', [BlogController::class, 'show'])->name('show');

    Route::middleware(['auth', EnsureAccountNotBlocked::class])->group(function (): void {
        Route::post('/{post:slug}/comments', [BlogCommentController::class, 'store'])
            ->middleware(['feature:blog.comments', 'throttle:6,1'])
            ->name('comments.store');
        Route::delete('/comments/{comment}', [BlogCommentController::class, 'destroy'])
            ->middleware('feature:blog.comments')
            ->name('comments.destroy');
        Route::post('/comments/{comment}/report', [BlogCommentController::class, 'report'])
            ->middleware(['feature:blog.comment_reports', 'throttle:10,1'])
            ->name('comments.report');
        Route::post('/{post:slug}/reactions', [BlogReactionController::class, 'toggle'])
            ->middleware(['feature:blog.reactions', 'throttle:60,1'])
            ->name('reactions.toggle');
    });
});

// Public CMS page viewer (Stage D) — kept last so it never shadows a more
// specific route above; PageController itself 404s anything not published.
// WEB-101 item G: `missing()` fires only once implicit route-model binding
// has already found no live Page with this slug — ResolvePageRedirectAction
// then checks page_redirects for a matching old_path before finally 404ing.
Route::get('/{page:slug}', PageController::class)
    ->name('pages.show')
    ->missing(fn (Request $request) => app(ResolvePageRedirectAction::class)->handle((string) $request->route('page')) ?? abort(404));
