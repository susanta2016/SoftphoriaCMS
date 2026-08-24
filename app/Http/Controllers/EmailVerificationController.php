<?php

namespace App\Http\Controllers;

use App\Actions\Registration\ResendVerificationEmailAction;
use App\Actions\Registration\VerifyEmailAction;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Email verification (point 6/verification tokens of the confirmed spec) —
 * fully independent of Subscription state, the only writer of
 * PendingVerification → Active. resend() is deliberately silent about
 * whether the submitted email belongs to any account, or is already
 * verified — see ResendVerificationEmailAction's docblock.
 */
class EmailVerificationController extends Controller
{
    public function verify(string $token, VerifyEmailAction $action): View
    {
        $user = $action->handle($token);

        $settings = app(SettingsRepository::class);
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Verify Email — {$chrome['siteName']}",
            'description' => 'Email verification.',
            'canonical' => url()->current(),
            'type' => 'website',
        ], $chrome['general']);

        return view('register.verified', [
            ...$chrome,
            'seo' => $seo,
            'verified' => $user !== null,
        ]);
    }

    public function resend(Request $request, ResendVerificationEmailAction $action): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ]);

        $notice = "If that email address has a pending registration, we've sent a new verification link.";

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Always the same outcome/message regardless of whether the email
        // exists, is already verified, or is genuinely unknown — see the
        // Action's docblock. Never branch the response on what handle()
        // did internally.
        $action->handle($validator->validated()['email']);

        return redirect()->back()->with('resend_notice', $notice);
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
