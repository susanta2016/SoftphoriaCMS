<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Public login/logout — the one thing missing from the existing
 * registration/verification flow. Thin per the project's controller
 * convention (RegistrationController): validation + redirect only, the
 * actual `Auth::attempt()` call lives in AuthenticateUserAction.
 *
 * Every view here is `robots: noindex, nofollow` (docs/development
 * instructions for SEO.docx §5) — transactional/account pages, never
 * sitemap or search-result candidates.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Log In — {$chrome['siteName']}",
            'description' => 'Log in to your account.',
            'canonical' => route('login'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('auth.login', [
            ...$chrome,
            'seo' => $seo,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('login')->withErrors($validator)->withInput($request->except('password'));
        }

        try {
            app(AuthenticateUserAction::class)->handle(
                $validator->validated()['email'],
                $validator->validated()['password'],
                $request->boolean('remember'),
            );
        } catch (ValidationException $exception) {
            return redirect()->route('login')->withErrors($exception->errors())->withInput($request->except('password'));
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'You have been logged out.');
    }

    /**
     * Site-wide header chrome, same source/shape as RegistrationController's
     * own private helper of the same name — not extracted to a shared
     * service since every consumer is a thin controller with this exact
     * one-line need (see RegistrationController's own docblock reasoning).
     *
     * @return array{siteName: string, tagline: ?string, logo: ?Media, general: array<string, mixed>}
     */
    private function siteChrome(SettingsRepository $settings): array
    {
        $general = $settings->all('general');
        $logoMediaId = $general['logo_media_id'] ?? null;

        return [
            'siteName' => ($general['site_name'] ?? null) ?: config('app.name'),
            'tagline' => $general['tagline'] ?? null,
            'logo' => $logoMediaId ? Media::find($logoMediaId) : null,
            'general' => $general,
        ];
    }
}
