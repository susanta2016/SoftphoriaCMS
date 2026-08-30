<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Commerce\Models\DownloadLog;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * The registered member's own download history — every successful track
 * download, whichever grant authorized it (a paid Entitlement or an active
 * Pro Membership). Distinct from /account/orders (what was bought) — a Pro
 * subscriber with no purchases has nothing there but still downloads tracks,
 * which is the gap this page closes. Reads DownloadLog only; every actual
 * download still goes through the existing, unmodified
 * music.tracks.download route/TrackDownloadController/
 * AuthorizeTrackDownloadAction. Scoped entirely through Auth::user()'s own
 * download rows — no route parameter, so there is nothing to manipulate to
 * reach another user's history.
 */
class DownloadController extends Controller
{
    public function __invoke(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);
        $user = Auth::user();

        $downloads = DownloadLog::query()
            ->where('user_id', $user->id)
            ->where('status', DownloadLogStatus::Succeeded)
            ->with(['track.album', 'track.single'])
            ->latest('created_at')
            ->paginate(15);

        $totalDownloads = DownloadLog::query()
            ->where('user_id', $user->id)
            ->where('status', DownloadLogStatus::Succeeded)
            ->count();

        $seo = SeoTagBuilder::build(null, [
            'title' => "Your Downloads — {$chrome['siteName']}",
            'description' => 'Your track download history.',
            'canonical' => route('account.downloads'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('account.downloads', [
            ...$chrome,
            'seo' => $seo,
            'downloads' => $downloads,
            'totalDownloads' => $totalDownloads,
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
