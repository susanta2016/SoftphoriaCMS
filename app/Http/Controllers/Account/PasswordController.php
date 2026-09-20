<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\ChangeAccountPasswordAction;
use App\Http\Controllers\Concerns\ResolvesSiteChrome;
use App\Http\Controllers\Controller;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * AUTH-005 — self-service password change + "log out other sessions"
 * (see ChangeAccountPasswordAction for the session-revocation mechanics).
 */
class PasswordController extends Controller
{
    use ResolvesSiteChrome;

    public function edit(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Change Password — {$chrome['siteName']}",
            'description' => 'Change your account password.',
            'canonical' => route('account.password.edit'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('account.password', [
            ...$chrome,
            'seo' => $seo,
        ]);
    }

    public function update(Request $request, ChangeAccountPasswordAction $action): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('account.password.edit')->withErrors($validator);
        }

        try {
            $action->handle(
                Auth::user(),
                $request->string('current_password')->toString(),
                $validator->validated()['password'],
                $request->session()->getId(),
            );
        } catch (ValidationException $exception) {
            return redirect()->route('account.password.edit')->withErrors($exception->errors());
        }

        return redirect()->route('account.password.edit')->with('status', 'Your password has been changed. You have been logged out of other devices.');
    }
}
