<?php

namespace App\Http\Controllers\Account;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Actions\GratitudeJournal\DeleteGratitudeJournalEntryAction;
use App\Actions\GratitudeJournal\UpdateGratitudeJournalEntryAction;
use App\Actions\GratitudeJournal\UpdateGratitudeReminderFrequencyAction;
use App\Enums\GratitudeReminderFrequency;
use App\Enums\LightPostSource;
use App\Http\Controllers\Controller;
use App\Models\LightPost;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * The registered member's own Gratitude Journal (Gratitude Journal audit
 * §3) — persistent, private-by-default-unless-chosen-otherwise short
 * entries reusing the existing light_posts table with source = journal
 * (App\Enums\LightPostSource), never a second table. Every query here is
 * scoped through Auth::user()->lightPosts()->journal(), the same
 * "operate through Auth::user() only" convention as ProfileController/
 * PasswordController/DownloadController — combined with an explicit
 * ownership + source check on every route-bound entry (own() below) since,
 * unlike those single-resource-per-user pages, individual journal entries
 * are addressable by id and therefore need that extra guard (see the
 * Gratitude Journal audit §8, which notes this codebase has no prior
 * precedent for a per-record, route-bound, owned-and-editable resource).
 *
 * No individual entry ever gets a public detail page or URL — see
 * LightPostController::show(), which explicitly rejects source = journal
 * rows even when public. This controller's own routes are the only way to
 * reach a specific entry, and only its owner can reach it here.
 */
class GratitudeJournalController extends Controller
{
    public function index(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);
        $user = Auth::user();

        $entries = $user->lightPosts()->journal()->latest()->get();

        $seo = SeoTagBuilder::build(null, [
            'title' => "Gratitude Journal — {$chrome['siteName']}",
            'description' => 'Your private Gratitude Journal.',
            'canonical' => route('account.gratitude-journal.index'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('account.gratitude-journal', [
            ...$chrome,
            'seo' => $seo,
            'entries' => $entries,
            'maxLength' => (int) config('features.gratitude_journal_max_length'),
            'reminderFrequency' => $user->gratitudeReminderFrequency(),
            'reminderFrequencies' => GratitudeReminderFrequency::cases(),
        ]);
    }

    public function store(Request $request, CreateGratitudeJournalEntryAction $action): RedirectResponse
    {
        $data = $this->validateEntry($request);

        // No default here — matches update() below. An unchecked HTML
        // checkbox is never sent at all, so "is_public absent" must mean
        // false (private), the same as it already correctly does on edit;
        // a hardcoded `true` default previously made an unchecked box on
        // the New Entry form silently save as Public regardless of what
        // the member selected. The form's own checkbox still defaults to
        // checked, which is what actually gives a *new* entry Public as
        // its starting visibility — this call has no opinion of its own.
        $action->handle(Auth::user(), $data['content'], $request->boolean('is_public'));

        return redirect()->route('account.gratitude-journal.index')->with('status', 'Your Gratitude Journal entry has been saved.');
    }

    public function update(Request $request, LightPost $lightPost, UpdateGratitudeJournalEntryAction $action): RedirectResponse
    {
        $this->authorizeOwnJournalEntry($lightPost);

        $data = $this->validateEntry($request);

        $action->handle($lightPost, $data['content'], $request->boolean('is_public'));

        return redirect()->route('account.gratitude-journal.index')->with('status', 'Your Gratitude Journal entry has been updated.');
    }

    public function destroy(LightPost $lightPost, DeleteGratitudeJournalEntryAction $action): RedirectResponse
    {
        $this->authorizeOwnJournalEntry($lightPost);

        $action->handle($lightPost);

        return redirect()->route('account.gratitude-journal.index')->with('status', 'Your Gratitude Journal entry has been deleted.');
    }

    public function updateReminderFrequency(Request $request, UpdateGratitudeReminderFrequencyAction $action): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'gratitude_reminder_frequency' => ['required', 'string', 'in:daily,weekly,none'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('account.gratitude-journal.index')->withErrors($validator);
        }

        $action->handle(Auth::user(), GratitudeReminderFrequency::from($validator->validated()['gratitude_reminder_frequency']));

        return redirect()->route('account.gratitude-journal.index')->with('status', 'Your reminder preference has been updated.');
    }

    /**
     * 404, not 403 — a member manipulating another member's entry id (or a
     * registration-sourced light_posts id) sees exactly the same response
     * as a genuinely nonexistent entry, the same "no signal about what
     * exists" posture LightPostController and RegistrationController's
     * honeypot already use elsewhere in this codebase.
     */
    private function authorizeOwnJournalEntry(LightPost $lightPost): void
    {
        abort_unless(
            $lightPost->source === LightPostSource::Journal && $lightPost->user_id === Auth::id(),
            404,
        );
    }

    /**
     * @return array{content: string}
     */
    private function validateEntry(Request $request): array
    {
        $request->merge(['content' => trim((string) $request->input('content'))]);

        $maxLength = (int) config('features.gratitude_journal_max_length');

        return $request->validate([
            'content' => ['required', 'string', "max:{$maxLength}"],
        ], [
            'content.required' => 'Please write your gratitude entry before saving.',
            'content.max' => "Gratitude Journal entries can be at most {$maxLength} characters.",
        ]);
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
