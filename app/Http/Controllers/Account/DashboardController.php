<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Only ever reads Auth::user() and its own real relations (profile,
 * subscription) — never a route parameter, so there's nothing here to
 * manipulate to view another account. No invented "Recent Activity"/orders
 * widgets: this app has no real activity feed yet, and a placeholder one
 * would be exactly the fabricated-data mistake the spec warns against.
 */
class DashboardController extends Controller
{
    public function __invoke(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);
        $user = Auth::user();

        $seo = SeoTagBuilder::build(null, [
            'title' => "Dashboard — {$chrome['siteName']}",
            'description' => 'Your account dashboard.',
            'canonical' => route('account.dashboard'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('account.dashboard', [
            ...$chrome,
            'seo' => $seo,
            'user' => $user,
            'profile' => $user->profile,
            'hasActiveMembership' => $user->hasActiveMembership(),
            'subscription' => $user->subscription,
            'subscriptionStatusLabel' => $user->subscription?->displayStatus()->getLabel(),
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
