<?php

namespace App\Http\Controllers\InspirationalResources;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\InspirationalResources\Actions\CreateResourceSubmissionAction;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Track;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * A single public destination: an introductory/informational section plus
 * the submission form (client-confirmed, final). Submissions are always
 * private administrative records — there is no public listing/library of
 * submitted resources, no per-submission public detail page, and no
 * separate public "Inspirational Resource" editorial model. The page
 * itself is a normal, indexable public page; individual submissions never
 * are.
 */
class InspirationalResourceSubmissionController extends Controller
{
    public function index(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Inspirational Resources — {$chrome['siteName']}",
            'description' => 'Share your story with us.',
            'canonical' => route('inspirational-resources.index'),
            'type' => 'website',
        ], $chrome['general']);

        return view('inspirational-resources.index', [
            ...$chrome,
            'seo' => $seo,
            'albums' => Album::query()->published()->orderBy('title')->get(['id', 'title']),
            'tracks' => Track::query()->published()->orderBy('title')->get(['id', 'title', 'album_id', 'single_id']),
        ]);
    }

    public function store(Request $request, CreateResourceSubmissionAction $action): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'related_album_id' => ['nullable', 'integer', 'exists:albums,id'],
            'related_track_id' => ['nullable', 'integer', 'exists:tracks,id'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('inspirational-resources.index')->withErrors($validator)->withInput();
        }

        $action->handle($validator->validated(), Auth::user());

        return redirect()->route('inspirational-resources.index')
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
