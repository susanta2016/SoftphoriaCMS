<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\ResolvesSiteChrome;
use App\Http\Controllers\Controller;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Public self-service "forgot password" request form (AUTH-004). The
 * admin-triggered equivalent (SendUserPasswordResetLinkAction) takes an
 * actor and writes an audit log entry because an admin is acting on
 * someone else's account; this is the user acting on their own, so it
 * calls the same underlying Password::broker()->sendResetLink() directly
 * — no actor, no audit log.
 *
 * Deliberately silent about whether the submitted email belongs to any
 * account — the same generic response either way, so the page never leaks
 * account existence.
 */
class PasswordResetLinkController extends Controller
{
    use ResolvesSiteChrome;

    public function create(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Forgot Password — {$chrome['siteName']}",
            'description' => 'Request a password reset link.',
            'canonical' => route('password.request'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('auth.forgot-password', [
            ...$chrome,
            'seo' => $seo,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('password.request')->withErrors($validator)->withInput();
        }

        // Always the same outcome/message regardless of whether the email
        // exists — never branch the response on Password::broker()'s
        // return status.
        Password::broker()->sendResetLink($validator->validated());

        return redirect()->route('password.request')
            ->with('status', "If that email address has an account, we've sent a password reset link.");
    }
}
