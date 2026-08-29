<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * The registered purchaser's digital purchase/download library — Orders,
 * their items, and download access. Deliberately separate from
 * TransactionController (/account/transactions), which stays the
 * PaymentTransaction ledger and is unchanged by this. Scoped entirely
 * through Auth::user()->orders() — no route parameter, so there is nothing
 * to manipulate to reach another user's orders (same shape as
 * TransactionController's own docblock reasoning). Download links reuse the
 * existing music.tracks.download route/TrackDownloadController/
 * AuthorizeTrackDownloadAction unchanged — this controller only reads.
 */
class OrderController extends Controller
{
    public function __invoke(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);
        $user = Auth::user();

        $orders = $user->orders()
            ->where('status', OrderStatus::Paid)
            ->with(['items.entitlement', 'items.album.tracks', 'items.single.track'])
            ->orderByDesc('paid_at')
            ->paginate(10);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Your Orders — {$chrome['siteName']}",
            'description' => 'Your digital music purchases and downloads.',
            'canonical' => route('account.orders'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('account.orders', [
            ...$chrome,
            'seo' => $seo,
            'orders' => $orders,
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
