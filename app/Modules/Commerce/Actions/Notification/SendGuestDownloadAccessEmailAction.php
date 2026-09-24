<?php

namespace App\Modules\Commerce\Actions\Notification;

use App\Enums\EmailRecipientType;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Support\IssuedEntitlement;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Called from HandleCheckoutSessionCompletedAction only when
 * MarkOrderPaidAction has just issued entitlements for the first time, and
 * only for a guest order ($order->isGuest() === true) — see
 * SendOrderConfirmationEmailAction for the registered counterpart. One email
 * per order, never one per item: every IssuedEntitlement's raw guest token
 * (only ever available here, in memory, right after issuance — Entitlement
 * itself stores only access_token_hash) is folded into a single
 * downloads.guest.show link. GuestDownloadController is the only consumer of
 * that raw token from this point on; it is never written to any database
 * table.
 */
class SendGuestDownloadAccessEmailAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    /**
     * @param  array<int, IssuedEntitlement>  $issuedEntitlements
     */
    public function handle(Order $order, array $issuedEntitlements): void
    {
        $tokenPairs = collect($issuedEntitlements)
            ->filter(fn (IssuedEntitlement $issued) => $issued->plainGuestToken !== null)
            ->map(fn (IssuedEntitlement $issued) => "{$issued->entitlement->public_id}.{$issued->plainGuestToken}")
            ->all();

        if ($tokenPairs === []) {
            return;
        }

        $accessUrl = route('downloads.guest.show', $order).'?'.http_build_query(['t' => $tokenPairs]);

        try {
            $this->mailer->send('guest_download_access', EmailRecipientType::User, $order->purchaser_email, [
                'order_items' => $order->items->pluck('item_title')->implode(', '),
                'order_total' => number_format((float) $order->total, 2),
                'download_access_url' => $accessUrl,
                ...$this->orderDetails($order, $issuedEntitlements),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Guest download access email failed to send', [
                'order_public_id' => $order->public_id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * The purchaser/order/link details the admin-authored template body can
     * reference. purchaser_name is always collected at guest checkout
     * (CheckoutController), but guarded anyway so a missing name never
     * leaves a stray "Greetings, ," in a real email. Expiry and download
     * limit come from the just-issued entitlements (their snapshotted
     * values), never re-resolved from current settings — the email must
     * describe exactly what the link will actually allow.
     *
     * @param  array<int, IssuedEntitlement>  $issuedEntitlements
     * @return array<string, string>
     */
    private function orderDetails(Order $order, array $issuedEntitlements): array
    {
        $purchasedAt = $order->paid_at ?? now();
        $entitlements = collect($issuedEntitlements)->map(fn (IssuedEntitlement $issued) => $issued->entitlement);

        // Earliest expiry / lowest limit across the order's items — the one
        // email must never promise more than every link actually delivers.
        $expiresAt = $entitlements->pluck('expires_at')->filter()->sort()->first();
        $maxDownloads = $entitlements->pluck('max_downloads')->reject(fn ($value) => $value === null)->min();

        $fullName = trim((string) $order->purchaser_name);

        return [
            'user_name' => $fullName !== '' ? $fullName : 'friend',
            'first_name' => $fullName !== '' ? Str::before($fullName, ' ') : 'friend',
            'user_email' => $order->purchaser_email,
            'item_title' => $order->items->pluck('item_title')->implode(', '),
            'item_type' => $order->items
                ->map(fn (OrderItem $item): string => $item->itemType() === 'track' ? 'Song' : Str::title($item->itemType()))
                ->unique()
                ->implode(', '),
            'purchase_date' => $purchasedAt->format('F j, Y'),
            'amount_paid' => $this->formatMoney((float) $order->total, (string) $order->currency),
            'order_id' => $order->public_id,
            'link_validity_window' => $expiresAt instanceof Carbon ? $this->formatWindow($purchasedAt, $expiresAt) : 'an unlimited time',
            'link_expiry_date' => $expiresAt instanceof Carbon ? $expiresAt->format('F j, Y') : 'Never',
            'max_downloads' => $maxDownloads !== null ? (string) $maxDownloads : 'Unlimited',
            'register_url' => route('register.show'),
        ];
    }

    private function formatMoney(float $amount, string $currency): string
    {
        $formatted = number_format($amount, 2);

        return in_array(strtolower($currency), ['', 'usd'], true) ? '$'.$formatted : strtoupper($currency).' '.$formatted;
    }

    private function formatWindow(Carbon $from, Carbon $until): string
    {
        $days = max(1, (int) round(abs($from->diffInDays($until))));

        return $days.' '.Str::plural('day', $days);
    }
}
