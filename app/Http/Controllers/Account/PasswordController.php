<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\ChangeAccountPasswordAction;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
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

        return redirect()->route('account.password.edit')->with('status', 'Your password has been changed.');
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
