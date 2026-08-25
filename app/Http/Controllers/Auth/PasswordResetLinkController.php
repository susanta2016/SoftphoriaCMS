<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Public self-service "forgot password" request form. The admin-triggered
 * equivalent (SendUserPasswordResetLinkAction/GenerateNewPasswordAction)
 * takes an actor and writes an audit log entry because an admin is acting
 * on someone else's account; this is the user acting on their own, so it
 * calls the same underlying Password::broker()->sendResetLink() directly
 * — no actor, no audit log, matching ResendVerificationEmailAction's own
 * "self-service action, no admin trail" precedent.
 *
 * Deliberately silent about whether the submitted email belongs to any
 * account — the same generic response either way — for the same reason
 * EmailVerificationController::resend() is silent (see its docblock):
 * the response must never leak account existence.
 */
class PasswordResetLinkController extends Controller
{
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
        // return status (RESET_LINK_SENT vs INVALID_USER).
        Password::broker()->sendResetLink($validator->validated());

        return redirect()->route('password.request')
            ->with('status', "If that email address has an account, we've sent a password reset link.");
    }

    /**
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
