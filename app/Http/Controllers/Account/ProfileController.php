<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\UpdateAccountProfileAction;
use App\Http\Controllers\Concerns\ResolvesSiteChrome;
use App\Http\Controllers\Controller;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * AUTH-005 — edits Auth::user() only. There is no user-id route parameter
 * to forge, so "can only edit your own profile" is enforced by never
 * accepting one, not by a per-request ownership check.
 *
 * /account/* is private/member-only content per the platform's standing
 * indexing rule — noindex, and never Sitemapable.
 */
class ProfileController extends Controller
{
    use ResolvesSiteChrome;

    public function edit(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);
        $user = Auth::user();

        $seo = SeoTagBuilder::build(null, [
            'title' => "Edit Profile — {$chrome['siteName']}",
            'description' => 'Update your account profile.',
            'canonical' => route('account.profile.edit'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('account.profile', [
            ...$chrome,
            'seo' => $seo,
            'user' => $user,
            'profile' => $user->profile,
        ]);
    }

    public function update(Request $request, UpdateAccountProfileAction $action): RedirectResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'bio' => ['nullable', 'string', 'max:65535'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('account.profile.edit')->withErrors($validator)->withInput();
        }

        $action->handle($user, $validator->validated());

        return redirect()->route('account.profile.edit')->with('status', 'Your profile has been updated.');
    }
}
