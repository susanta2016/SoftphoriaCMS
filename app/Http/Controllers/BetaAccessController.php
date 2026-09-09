<?php

namespace App\Http\Controllers;

use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * The temporary Beta Access password page (BetaAccessGate's own docblock
 * explains the wider mechanism). Deliberately thin, mirroring the project's
 * existing controller convention (ContactController/RegistrationController):
 * validation and the constant-time password check only.
 */
class BetaAccessController extends Controller
{
    public function show(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Beta Access — {$chrome['siteName']}",
            'description' => 'This website is currently in private beta.',
            'canonical' => route('beta.show'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('beta-access.show', [
            ...$chrome,
            'seo' => $seo,
        ]);
    }

    public function attempt(Request $request): RedirectResponse
    {
        // Same honeypot pattern as every other public form in this project
        // (ContactController::store()'s own docblock) — a bot that fills
        // every input trips it and is silently sent back to the same
        // generic error, with no signal it was caught differently.
        if (filled($request->input('hp_website'))) {
            return $this->rejected();
        }

        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->rejected();
        }

        $expected = (string) config('beta.password');
        $supplied = (string) $request->input('password');

        // blank() guards a configured-but-empty password: hash_equals('', '')
        // is true, which would otherwise let an empty submission through
        // whenever the password env var is unset while the gate is enabled.
        if (blank($expected) || ! hash_equals($expected, $supplied)) {
            return $this->rejected();
        }

        // Session ID rotation on privilege escalation — standard practice,
        // and safe here: CSRF verification for this very request already
        // ran (VerifyCsrfToken, before this controller), so regenerating
        // only affects the token issued for whatever page loads next.
        $request->session()->put('beta_access_granted', true);
        $request->session()->regenerate();

        $intended = $request->session()->pull('beta_access_intended_url');

        return redirect()->to($intended ?: route('home'));
    }

    /**
     * The internal check Nginx's auth_request directive calls before
     * serving anything under /storage/... (docker/nginx/prod.conf) — the
     * one gap BetaAccessGate itself cannot close, since a real storage file
     * is served by Nginx directly and never reaches Laravel's middleware
     * stack. Deliberately the exact same session question, so a visitor
     * with beta_access_granted needs to authenticate only once for both
     * pages and storage files alike.
     *
     * Nginx marks the location `internal;`, so this route is unreachable
     * from outside the container regardless of what it returns.
     */
    public function authCheck(Request $request): Response
    {
        if (! config('beta.enabled')) {
            return response()->noContent();
        }

        return $request->session()->get('beta_access_granted') === true
            ? response()->noContent()
            : response()->noContent(401);
    }

    private function rejected(): RedirectResponse
    {
        // Deliberately one generic message regardless of what went wrong
        // (blank field, honeypot, wrong password) — never reveals which
        // part of the check failed.
        return redirect()->route('beta.show')
            ->withErrors(['password' => 'Incorrect password. Please try again.']);
    }

    /**
     * @return array{siteName: string, general: array<string, mixed>}
     */
    private function siteChrome(SettingsRepository $settings): array
    {
        $general = $settings->all('general');

        return [
            'siteName' => ($general['site_name'] ?? null) ?: 'All The Things Light',
            'general' => $general,
        ];
    }
}
