<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\User;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Completes the flow AppServiceProvider::routeResetPasswordThroughEmailTemplates()
 * already anticipated — this is the "password.reset" route it was waiting
 * on (see that method's own docblock). Uses Laravel's stock password
 * broker end to end: securely generated/hashed/expiring/single-use tokens
 * are entirely Laravel's `password_reset_tokens` table + Password facade,
 * never reimplemented here.
 */
class NewPasswordController extends Controller
{
    public function create(SettingsRepository $settings, Request $request, string $token): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Reset Password — {$chrome['siteName']}",
            'description' => 'Choose a new password.',
            'canonical' => url()->current(),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('auth.reset-password', [
            ...$chrome,
            'seo' => $seo,
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('password.reset', [
                'token' => $request->string('token'),
                'email' => $request->string('email'),
            ])->withErrors($validator);
        }

        $data = $validator->validated();

        $status = Password::broker()->reset(
            $data,
            function (User $user, string $password): void {
                $user->password = Hash::make($password);
                $user->remember_token = Str::random(60);
                $user->save();

                Event::dispatch(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return redirect()->route('password.reset', [
                'token' => $data['token'],
                'email' => $data['email'],
            ])->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. Please log in.');
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
