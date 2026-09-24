<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The purchaser/order/download-access {{variables}} shared by both purchase
 * emails (SendOrderConfirmationEmailAction, SendGuestDownloadAccessEmailAction)
 * — built in one place so the two keys' admin-authored bodies can use the
 * same variable names and never drift apart. Expiry and download limit come
 * from the just-issued entitlements (their snapshotted values), never
 * re-resolved from current settings — the email must describe exactly what
 * the purchase actually allows.
 */
final class OrderEmailVariables
{
    /**
     * @param  array<int, IssuedEntitlement>  $issuedEntitlements
     * @return array<string, string>
     */
    public static function for(Order $order, array $issuedEntitlements): array
    {
        $purchasedAt = $order->paid_at ?? now();
        $entitlements = collect($issuedEntitlements)->map(fn (IssuedEntitlement $issued) => $issued->entitlement);

        // Earliest expiry / lowest limit across the order's items — one
        // email must never promise more than every item actually delivers.
        $expiresAt = $entitlements->pluck('expires_at')->filter()->sort()->first();
        $maxDownloads = $entitlements->pluck('max_downloads')->reject(fn ($value) => $value === null)->min();

        // purchaser_name is always collected at checkout (CheckoutController),
        // but guarded so a missing name never leaves "Greetings, ," behind.
        $fullName = trim((string) $order->purchaser_name);
        $firstItem = $order->items->first();

        return [
            'user_name' => $fullName !== '' ? $fullName : 'friend',
            'first_name' => $fullName !== '' ? Str::before($fullName, ' ') : 'friend',
            'user_email' => (string) $order->purchaser_email,
            'item_title' => $order->items->pluck('item_title')->implode(', '),
            'item_type' => $order->items
                ->map(fn (OrderItem $item): string => $item->itemType() === 'track' ? 'Song' : Str::title($item->itemType()))
                ->unique()
                ->implode(', '),
            'item_url' => $firstItem ? self::itemUrl($firstItem) : route('music.index'),
            'order_items' => $order->items->pluck('item_title')->implode(', '),
            'order_total' => number_format((float) $order->total, 2),
            'purchase_date' => $purchasedAt->format('F j, Y'),
            'amount_paid' => self::formatMoney((float) $order->total, (string) $order->currency),
            'order_id' => (string) $order->public_id,
            'link_validity_window' => $expiresAt instanceof Carbon ? self::formatWindow($purchasedAt, $expiresAt) : 'an unlimited time',
            'link_expiry_date' => $expiresAt instanceof Carbon ? $expiresAt->format('F j, Y') : 'Never',
            'max_downloads' => $maxDownloads !== null ? (string) $maxDownloads : 'Unlimited',
            'register_url' => route('register.show'),
        ];
    }

    private static function itemUrl(OrderItem $item): string
    {
        $model = $item->item();

        return match (true) {
            $model instanceof Track => $model->publicUrl(),
            $model instanceof Album => route('music.albums.show', $model),
            $model instanceof Single => route('music.singles.show', $model),
            default => route('music.index'),
        };
    }

    private static function formatMoney(float $amount, string $currency): string
    {
        $formatted = number_format($amount, 2);

        return in_array(strtolower($currency), ['', 'usd'], true) ? '$'.$formatted : strtoupper($currency).' '.$formatted;
    }

    private static function formatWindow(Carbon $from, Carbon $until): string
    {
        $days = max(1, (int) round(abs($from->diffInDays($until))));

        return $days.' '.Str::plural('day', $days);
    }
}
