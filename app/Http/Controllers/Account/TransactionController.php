<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\Commerce\Models\PaymentTransaction;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Seo\SeoTagBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * The user's full payment ledger: one-off Single/Album purchases (via their
 * Orders) and Pro Membership renewals (via their Subscription), combined and
 * sorted by date. A single OR'd query rather than two lists merged in PHP —
 * PaymentTransaction can reference an order, a subscription, or (per its own
 * booted() guard) both, so a query that checked only one relation could
 * double-count or miss a row.
 *
 * Scoped entirely through Auth::user()'s own order/subscription ownership —
 * there is no route parameter, so there is nothing to manipulate to reach
 * another user's transactions.
 */
class TransactionController extends Controller
{
    public function __invoke(SettingsRepository $settings): View
    {
        $chrome = $this->siteChrome($settings);
        $user = Auth::user();

        $transactions = PaymentTransaction::query()
            ->where(function ($query) use ($user): void {
                $query->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
                    ->orWhereHas('subscription', fn ($q) => $q->where('user_id', $user->id));
            })
            ->with(['order.items', 'subscription'])
            ->orderByDesc('occurred_at')
            ->paginate(15);

        $seo = SeoTagBuilder::build(null, [
            'title' => "Transaction History — {$chrome['siteName']}",
            'description' => 'Your purchase and payment history.',
            'canonical' => route('account.transactions'),
            'type' => 'website',
            'robots' => SeoTagBuilder::ROBOTS_NOINDEX,
        ], $chrome['general']);

        return view('account.transactions', [
            ...$chrome,
            'seo' => $seo,
            'transactions' => $transactions,
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
