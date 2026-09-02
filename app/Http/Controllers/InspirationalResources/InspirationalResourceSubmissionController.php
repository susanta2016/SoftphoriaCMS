<?php

namespace App\Http\Controllers\InspirationalResources;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\InspirationalResources\Actions\CreateResourceSubmissionAction;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * The "Submit Your Writing" form page (client-confirmed 2026-09-02: a
 * dedicated page, reached from the Inspirational Resources listing and
 * from Poetry/Prose's sidebar — see InspirationalResourceController for the
 * public listing/detail pages this is separate from). A submission is a
 * private administrative record until an admin approves it; approving it
 * is what makes it appear on the public listing/detail pages.
 */
class InspirationalResourceSubmissionController extends Controller
{
    public function create(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Submit Your Writing — {$chrome['siteName']}",
            'description' => 'Share your story with us.',
            'canonical' => route('inspirational-resources.create'),
            'type' => 'website',
        ], $chrome['general']);

        return view('inspirational-resources.create', [
            ...$chrome,
            'seo' => $seo,
        ]);
    }

    public function store(Request $request, CreateResourceSubmissionAction $action): RedirectResponse
    {
        $user = Auth::user();

        // A logged-in user never sees or fills in name/email on the form
        // (see inspirational-resources/create.blade.php's @guest block), so
        // these aren't required from the request for them.
        $validator = Validator::make($request->all(), [
            'name' => [$user ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$user ? 'sometimes' : 'required', 'string', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'reference_url' => ['nullable', 'url', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('inspirational-resources.create')->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        // Sourced from the authenticated account, never trusted from the
        // request — a logged-in user's name/email are fetched internally
        // rather than taking whatever the client happened to submit.
        if ($user) {
            $data['name'] = $user->name;
            $data['email'] = $user->email;
        }

        $action->handle($data, $user);

        return redirect()->route('inspirational-resources.create')
            ->with('status', 'Thank you — your submission has been received.');
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
