<?php

namespace App\Modules\Commerce\Actions\Order;

use App\Models\User;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckAlbumReadinessAction;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckSingleReadinessAction;
use App\Modules\Commerce\Exceptions\PurchaseNotReadyException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Services\Pricing\GlobalPricingResolver;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use Illuminate\Support\Facades\DB;

/**
 * Creates an Order + its one OrderItem for a Single or Album purchase, guest
 * or registered — the entry point every future checkout path (customer
 * frontend, a later "buy for a member" admin action, etc.) is meant to call
 * rather than building an Order by hand. Re-validates purchase readiness
 * server-side (§9/§15) even though nothing calls this from an unvalidated
 * frontend yet — the whole point of "never trust price/eligibility from the
 * request" is that this check has to live here, not only in whatever UI
 * calls it later. Price is always resolved from Global Pricing at this exact
 * moment and snapshotted — see GlobalPricingResolver's docblock.
 */
class CreatePendingOrderAction
{
    public function __construct(
        private readonly CheckAlbumReadinessAction $checkAlbum,
        private readonly CheckSingleReadinessAction $checkSingle,
        private readonly GlobalPricingResolver $pricing,
    ) {}

    /**
     * @throws PurchaseNotReadyException
     */
    public function handle(Album|Single $item, ?User $user, string $purchaserEmail, ?string $purchaserName = null): Order
    {
        $readiness = $item instanceof Album ? $this->checkAlbum->handle($item) : $this->checkSingle->handle($item);

        if (! $readiness->ready) {
            throw PurchaseNotReadyException::forIssues($item->title, $readiness->issues);
        }

        $price = $item instanceof Album ? $this->pricing->fullAlbumPrice() : $this->pricing->perSongPrice();
        $currency = 'usd';

        return DB::transaction(function () use ($item, $user, $purchaserEmail, $purchaserName, $price, $currency): Order {
            $order = new Order;
            $order->user_id = $user?->getKey();
            $order->purchaser_email = $user?->email ?? $purchaserEmail;
            $order->purchaser_name = $purchaserName;
            $order->currency = $currency;
            $order->subtotal = $price;
            $order->total = $price;
            $order->save();

            $item_ = new OrderItem;
            $item_->order_id = $order->getKey();
            $item_->album_id = $item instanceof Album ? $item->getKey() : null;
            $item_->single_id = $item instanceof Single ? $item->getKey() : null;
            $item_->item_title = $item->title;
            $item_->quantity = 1;
            $item_->unit_price = $price;
            $item_->currency = $currency;
            $item_->subtotal = $price;
            $item_->total = $price;
            $item_->save();

            return $order;
        });
    }
}
