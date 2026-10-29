<?php

namespace App\Modules\Commerce\Actions\Order;

use App\Models\User;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\DownloadLog;
use App\Modules\Commerce\Models\Entitlement;
use App\Modules\Commerce\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Attaches a guest's earlier purchases to their account once — and only
 * once — that account has *proven* ownership of the purchase email, i.e.
 * email_verified_at is set (VerifyEmailAction is its only writer, and an
 * email change resets it — see UpdateAccountProfileAction). An unverified
 * account, or one registered under any other address, claims nothing:
 * there is deliberately no "claim by typing an email" path.
 *
 * Ownership is transferred in place — the same Order, OrderItem,
 * PaymentTransaction and Entitlement rows simply gain a user_id. Nothing is
 * re-issued, so paid_at, max_downloads, every download counter
 * (entitlements.downloads_used and entitlement_download_counters) and
 * expires_at are exactly what they were as a guest — becoming a member
 * never resets or extends a quota, and never creates a duplicate order or
 * entitlement. The guest's past DownloadLog rows for those entitlements are
 * attributed to the account too, so they appear in /account/downloads.
 *
 * Idempotent: only rows whose user_id is still null are touched, so running
 * it on every verification and every sign-in is safe.
 */
class ClaimGuestPurchasesAction
{
    /**
     * @return int number of orders claimed
     */
    public function handle(User $user): int
    {
        if ($user->email_verified_at === null || blank($user->email)) {
            return 0;
        }

        $email = Str::lower(trim($user->email));

        return DB::transaction(function () use ($user, $email): int {
            $orderIds = Order::query()
                ->whereNull('user_id')
                ->whereIn('status', [OrderStatus::Paid, OrderStatus::Refunded])
                ->whereRaw('LOWER(TRIM(purchaser_email)) = ?', [$email])
                ->lockForUpdate()
                ->pluck('id');

            if ($orderIds->isEmpty()) {
                return 0;
            }

            Order::query()->whereIn('id', $orderIds)->whereNull('user_id')->update(['user_id' => $user->getKey()]);

            $entitlementIds = Entitlement::query()
                ->whereHas('orderItem', fn ($query) => $query->whereIn('order_id', $orderIds))
                ->whereNull('user_id')
                ->pluck('id');

            Entitlement::query()->whereIn('id', $entitlementIds)->update(['user_id' => $user->getKey()]);

            DownloadLog::query()
                ->whereIn('entitlement_id', $entitlementIds)
                ->whereNull('user_id')
                ->update(['user_id' => $user->getKey()]);

            return $orderIds->count();
        });
    }
}
