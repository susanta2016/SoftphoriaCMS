<?php

namespace App\Http\Controllers\InspirationalResources;

use App\Http\Controllers\Controller;
use App\Models\LightPost;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;

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
 * Private journal entries only (client-confirmed, 2026-09-04) — a Public
 * journal entry's exposure is the homepage carousel instead
 * (HomeController::latestGratitudeEntries(), untouched by this controller);
 * it does not also appear here. This is the one and only place a Private
 * entry is ever shown to anyone besides its author — the public
 * LightPostController::show() detail route still rejects every
 * journal-sourced row (public or private) regardless. The route itself
 * (routes/web.php) is what keeps a guest out of this page entirely.
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

        $entries = LightPost::query()->journal()->where('is_public', false)->with('user')->latest()->orderByDesc('id')->paginate(10)->withQueryString();

        $seo = SeoTagBuilder::build(null, [
            'title' => "Gratitude Journal — {$chrome['siteName']}",
            'description' => 'Read private gratitude shared within our member community.',
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
