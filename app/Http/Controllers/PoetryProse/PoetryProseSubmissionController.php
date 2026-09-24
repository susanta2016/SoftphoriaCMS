<?php

namespace App\Http\Controllers\PoetryProse;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\PoetryProse\Actions\CreatePoetryProseSubmissionAction;
use App\Modules\PoetryProse\Models\PoetryProseSubmission;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The Poetry/Prose (Light Posts) "Submit Your Writing" page (2026-09-24) —
 * reached from the Poetry/Prose sidebar's submit CTA. Same fields and
 * behaviour as Inspirational Resources' submission form, but its own
 * categories and its own inbox (PoetryProseSubmission, admin Poetry/Prose →
 * Submissions). Spam protection: the hp_website honeypot (silently
 * discarded, same as ContactController) plus the route's throttle.
 */
class PoetryProseSubmissionController extends Controller
{
    /**
     * Fallback copy, admin-editable via Website Setup → Settings →
     * Poetry/Prose (which shows these same defaults until saved).
     */
    public const DEFAULT_HEADING = 'Submit Your Writing';

    public const DEFAULT_INTRO = "Has a moment, a memory, or a reflection stirred something in you? We'd love to read your words. Share your testimony, essay, poem, or reflection below — our team reads every submission.";

    private const THANK_YOU = 'Thank you — your writing has been received.';

    public function create(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);
        $heading = $settings->get('poetry_prose', 'submit_page_heading') ?: self::DEFAULT_HEADING;
        $intro = $settings->get('poetry_prose', 'submit_page_intro') ?: self::DEFAULT_INTRO;

        $seo = SeoTagBuilder::build(null, [
            'title' => "{$heading} — {$chrome['siteName']}",
            'description' => 'Share your writing with us.',
            'canonical' => route('poetry-prose.create'),
            'type' => 'website',
        ], $chrome['general']);

        return view('poetry-prose.submit', [
            ...$chrome,
            'seo' => $seo,
            'heading' => $heading,
            'intro' => $intro,
        ]);
    }

    public function store(Request $request, CreatePoetryProseSubmissionAction $action): RedirectResponse
    {
        // A real visitor never sees or fills in this field (see the
        // honeypot markup in poetry-prose/submit.blade.php); a bot that
        // does gets the same thank-you as a genuine submission, and
        // nothing is saved or emailed.
        if (filled($request->input('hp_website'))) {
            return redirect()->route('poetry-prose.create')->with('status', self::THANK_YOU);
        }

        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => [$user ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$user ? 'sometimes' : 'required', 'string', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(PoetryProseSubmission::CATEGORY_OPTIONS)],
            'theme' => ['required', 'string', Rule::in(PoetryProseSubmission::THEME_OPTIONS)],
            'message' => ['required', 'string', 'max:5000'],
            'reference_url' => ['nullable', 'url', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('poetry-prose.create')->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        // Sourced from the account, never trusted from the request — same
        // as the Inspirational Resources form.
        if ($user) {
            $data['name'] = $user->displayName();
            $data['email'] = $user->email;
        }

        $action->handle($data, $user);

        return redirect()->route('poetry-prose.create')->with('status', self::THANK_YOU);
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
