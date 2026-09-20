<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Concerns\ResolvesSiteChrome;
use App\Http\Controllers\Controller;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Public self-registration (AUTH-001) — Guest/Registered only, thin per
 * this project's controller convention (ContactController): validation
 * only, the actual account creation lives in RegisterUserAction.
 *
 * Every view here is `robots: noindex, nofollow` — transactional pages,
 * never sitemap or search-result candidates.
 *
 * Spam protection: the same honeypot pattern as ContactController::store()
 * (`hp_website`, hidden from real visitors via CSS). Project-wide rule:
 * every public-facing submission form gets this same honeypot.
 */
class RegisteredUserController extends Controller
{
    use ResolvesSiteChrome;

    public function create(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Register — {$chrome['siteName']}",
            'description' => 'Create your free account.',
            'canonical' => route('register'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('auth.register', [
            ...$chrome,
            'seo' => $seo,
        ]);
    }

    public function store(Request $request, RegisterUserAction $action): RedirectResponse
    {
        // A real visitor never sees or fills in this field. A bot that
        // blindly fills every input trips it, and the request is discarded
        // silently: same redirect a genuine submission would get, so the
        // bot gets no signal that it was caught.
        if (filled($request->input('hp_website'))) {
            return redirect()->route('register.thank-you');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('register')->withErrors($validator)->withInput($request->except('password', 'password_confirmation'));
        }

        $action->handle($validator->validated());

        return redirect()->route('register.thank-you');
    }

    public function thankYou(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Thank You — {$chrome['siteName']}",
            'description' => 'Registration confirmation.',
            'canonical' => route('register.thank-you'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('auth.register-thank-you', [
            ...$chrome,
            'seo' => $seo,
        ]);
    }
}
