<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResendVerificationEmailAction;
use App\Actions\Auth\VerifyEmailAction;
use App\Http\Controllers\Concerns\ResolvesSiteChrome;
use App\Http\Controllers\Controller;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Email verification (AUTH-003) — the only writer of the
 * PendingVerification → Active transition (see VerifyEmailAction).
 * resend() is deliberately silent about whether the submitted email
 * belongs to any account, or is already verified — see
 * ResendVerificationEmailAction's docblock.
 *
 * Reaching Active here only means the account can be trusted to receive
 * mail at this address — it is NOT the same thing as "authenticated".
 * EnsureAccountIsUsable (AUTH-005) still lets a PendingVerification user
 * log in and use /account/*; a future verified-only gate (e.g. commenting)
 * checks $user->status directly, it does not reuse this controller.
 */
class EmailVerificationController extends Controller
{
    use ResolvesSiteChrome;

    public function verify(string $token, VerifyEmailAction $action, SettingsRepository $settings): View
    {
        $user = $action->handle($token);

        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Verify Email — {$chrome['siteName']}",
            'description' => 'Email verification.',
            'canonical' => url()->current(),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('auth.verified', [
            ...$chrome,
            'seo' => $seo,
            'verified' => $user !== null,
        ]);
    }

    public function resend(Request $request, ResendVerificationEmailAction $action): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Always the same outcome/message regardless of whether the email
        // exists, is already verified, or is genuinely unknown — never
        // branch the response on what handle() did internally.
        $action->handle($validator->validated()['email']);

        return redirect()->back()
            ->with('status', "If that email address has a pending registration, we've sent a new verification link.");
    }
}
