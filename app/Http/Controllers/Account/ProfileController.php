<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\UpdateAccountProfileAction;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Edits Auth::user() only — there is no user-id route parameter to forge,
 * so "can only edit your own profile" is enforced by never accepting one,
 * not by a per-request ownership check. Validation builds an explicit
 * whitelist array (`$data` below) that is the only thing ever handed to
 * UpdateAccountProfileAction — id/status/roles/membership are never read
 * from the request at all, regardless of what a client posts.
 */
class ProfileController extends Controller
{
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

        try {
            $action->handle($user, $validator->validated());
        } catch (ValidationException $exception) {
            return redirect()->route('account.profile.edit')->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('account.profile.edit')->with('status', 'Your profile has been updated.');
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
