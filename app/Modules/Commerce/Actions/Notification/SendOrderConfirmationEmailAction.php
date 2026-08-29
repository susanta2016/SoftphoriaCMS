<?php

namespace App\Modules\Commerce\Actions\Notification;

use App\Enums\EmailRecipientType;
use App\Modules\Commerce\Models\Order;
use App\Shared\Services\Notifications\TemplatedMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Called from HandleCheckoutSessionCompletedAction only when MarkOrderPaidAction
 * has just issued entitlements for the first time (never on a webhook retry —
 * see that action's own idempotency guard), and only for a registered
 * purchaser ($order->isGuest() === false); see
 * SendGuestDownloadAccessEmailAction for the guest counterpart. Points the
 * purchaser at /account/orders rather than embedding any download link
 * itself — a registered user's download access already works via their
 * existing session (TrackDownloadController), nothing new to prove here.
 */
class SendOrderConfirmationEmailAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    public function handle(Order $order): void
    {
        try {
            $this->mailer->send('order_confirmation', EmailRecipientType::User, $order->purchaser_email, [
                'user_name' => $order->purchaser_name,
                'order_items' => $order->items->pluck('item_title')->implode(', '),
                'order_total' => number_format((float) $order->total, 2),
                'account_orders_url' => route('account.orders'),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Order confirmation email failed to send', [
                'order_public_id' => $order->public_id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
