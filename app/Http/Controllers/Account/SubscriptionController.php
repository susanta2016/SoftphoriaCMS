<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\Commerce\Enums\PaymentTransactionType;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only, like DashboardController — no Action class since there is no
 * mutation here, only Auth::user()'s own real Subscription row and the
 * PaymentTransaction rows it owns (never another user's, since there is no
 * route parameter to even attempt one with). Renewal history is the
 * subscription's own `subscription_invoice_paid`/`subscription_invoice_failed`
 * transactions — Subscription is a HasOne (one row per user, ever), so past
 * renewals live only as transaction rows, never as separate Subscription
 * records.
 */
class SubscriptionController extends Controller
{
    public function __invoke(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);
        $user = Auth::user();
        $subscription = $user->subscription;

        $renewals = $subscription
            ?->transactions()
            ->whereIn('type', [PaymentTransactionType::SubscriptionInvoicePaid, PaymentTransactionType::SubscriptionInvoiceFailed])
            ->orderByDesc('occurred_at')
            ->get();

        $seo = SeoTagBuilder::build(null, [
            'title' => "Subscription — {$chrome['siteName']}",
            'description' => 'Your Pro Membership subscription and renewal history.',
            'canonical' => route('account.subscription'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('account.subscription', [
            ...$chrome,
            'seo' => $seo,
            'subscription' => $subscription,
            'hasActiveMembership' => $user->hasActiveMembership(),
            'renewals' => $renewals ?? collect(),
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
