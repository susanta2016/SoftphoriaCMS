<?php

namespace App\Modules\Commerce\Actions\Notification;

use App\Enums\EmailRecipientType;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\IssuedEntitlement;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
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
            ]);
        } catch (Throwable $exception) {
            Log::warning('Guest download access email failed to send', [
                'order_public_id' => $order->public_id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
