<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Http\Controllers\Concerns\ResolvesSiteChrome;
use App\Http\Controllers\Controller;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Public login/logout (AUTH-002). Thin per this project's controller
 * convention: validation only, the actual Auth::attempt() call lives in
 * AuthenticateUserAction.
 */
class AuthenticatedSessionController extends Controller
{
    use ResolvesSiteChrome;

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

    public function store(Request $request, AuthenticateUserAction $action): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('login')->withErrors($validator)->withInput($request->except('password'));
        }

        try {
            $action->handle(
                $validator->validated()['email'],
                $validator->validated()['password'],
                $request->boolean('remember'),
            );
        } catch (ValidationException $exception) {
            return redirect()->route('login')->withErrors($exception->errors())->withInput($request->except('password'));
        }

        $request->session()->regenerate();

        // AUTH-005 (account.profile.edit) lands in a later commit — guarded
        // the same way the auth views guard route('login') until it exists.
        $default = Route::has('account.profile.edit') ? route('account.profile.edit') : route('home');

        return redirect()->intended($default);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'You have been logged out.');
    }
}
