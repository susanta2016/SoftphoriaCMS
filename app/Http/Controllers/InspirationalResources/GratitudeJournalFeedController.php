<?php

namespace App\Http\Controllers\InspirationalResources;

use App\Http\Controllers\Controller;
use App\Models\LightPost;
use App\Models\Media;
use App\Models\Reaction;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The Gratitude Journal shared member feed — READ-ONLY, a subpage of
 * Inspirational Resources (client suggestion: "maybe as a subpage to the
 * 'Inspirations' landing page"). Deliberately a *separate* controller from
 * App\Http\Controllers\Account\GratitudeJournalController, which alone
 * owns every create/edit/delete/visibility/reminder action on a member's
 * own entries — this controller has exactly one action (index) and no
 * store/update/destroy at all, so there is no duplicate CRUD path to a
 * light_posts row.
 *
 * "For Community" entries only (Gratitude Journal three-state visibility
 * change, 2026-09-05) — this is exactly the behavior the old is_public =
 * false ("Private") state already had; that state was renamed/reused as
 * Community rather than reinterpreted, so this page's actual content is
 * unchanged for every pre-existing row (see the visibility migration's own
 * docblock). A Public journal entry's exposure is the homepage carousel
 * instead (HomeController::latestGratitudeEntries(), untouched by this
 * controller) — it does not also appear here. A genuinely Private entry
 * (the new state) never appears here either — only in its owner's own
 * Account "Your Entries". The public LightPostController::show() detail
 * route still rejects every journal-sourced row regardless of visibility.
 * The route itself (routes/web.php) is what keeps a guest out of this page
 * entirely.
 */
class GratitudeJournalFeedController extends Controller
{
    /**
     * orderByDesc('id') as a tiebreaker alongside latest() — several
     * entries can share the same created_at second on a busy feed, and
     * latest() alone gives those rows an undefined relative order, which
     * would make pagination boundaries nondeterministic (same reasoning as
     * InspirationalResourceController::index()'s own ordering).
     */
    public function index(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);

        $entries = LightPost::query()->journal()->community()->with('user')->withCount('reactions')->latest()->orderByDesc('id')->paginate(10)->withQueryString();

        $this->markReactedEntries($entries->getCollection());

        $seo = SeoTagBuilder::build(null, [
            'title' => "Gratitude Journal — {$chrome['siteName']}",
            'description' => 'Read gratitude shared within our member community.',
            'canonical' => route('inspirational-resources.gratitude-journal'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('inspirational-resources.gratitude-journal', [
            ...$chrome,
            'seo' => $seo,
            'entries' => $entries,
        ]);
    }

    /**
     * Sets a transient `userReacted` attribute on each entry for the
     * current viewer — one query total for the whole page (10 entries),
     * never one query per entry. Deliberately separate from
     * `withCount('reactions')` above, which already gives every entry its
     * total count in the same single paginated query.
     *
     * @param  Collection<int, LightPost>  $entries
     */
    private function markReactedEntries(Collection $entries): void
    {
        if (! Auth::check() || $entries->isEmpty()) {
            $entries->each(fn (LightPost $entry) => $entry->userReacted = false);

            return;
        }

        $reactedIds = Reaction::query()
            ->where('reactable_type', LightPost::class)
            ->where('user_id', Auth::id())
            ->whereIn('reactable_id', $entries->pluck('id'))
            ->pluck('reactable_id')
            ->all();

        $entries->each(fn (LightPost $entry) => $entry->userReacted = in_array($entry->id, $reactedIds, true));
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
