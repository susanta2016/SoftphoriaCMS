<?php

namespace App\Modules\Commerce\Actions\Cart;

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
 * Appends one more OrderItem to an already-created pending Order — the
 * companion to CreatePendingOrderAction, which only ever makes a fresh Order
 * with its first item. The Music frontend's "cart" is session-held
 * ({type, id} pairs — see CartController) until checkout, at which point the
 * checkout controller calls CreatePendingOrderAction once for the first item
 * and this action for every item after it, so the whole cart lands in one
 * Order. Re-validates readiness the same way CreatePendingOrderAction does —
 * never trust that a still-in-session item is still purchasable by the time
 * checkout actually runs.
 */
class AddToCartAction
{
    public function __construct(
        private readonly CheckAlbumReadinessAction $checkAlbum,
        private readonly CheckSingleReadinessAction $checkSingle,
        private readonly GlobalPricingResolver $pricing,
    ) {}

    /**
     * @throws PurchaseNotReadyException
     */
    public function handle(Order $order, Album|Single $item): Order
    {
        $isAlbum = $item instanceof Album;

        $alreadyInCart = $order->items()
            ->where($isAlbum ? 'album_id' : 'single_id', $item->getKey())
            ->exists();

        if ($alreadyInCart) {
            return $order;
        }

        $readiness = $isAlbum ? $this->checkAlbum->handle($item) : $this->checkSingle->handle($item);

        if (! $readiness->ready) {
            throw PurchaseNotReadyException::forIssues($item->title, $readiness->issues);
        }

        $price = $isAlbum ? $this->pricing->fullAlbumPrice() : $this->pricing->perSongPrice();

        return DB::transaction(function () use ($order, $item, $isAlbum, $price): Order {
            $orderItem = new OrderItem;
            $orderItem->order_id = $order->getKey();
            $orderItem->album_id = $isAlbum ? $item->getKey() : null;
            $orderItem->single_id = $isAlbum ? null : $item->getKey();
            $orderItem->item_title = $item->title;
            $orderItem->quantity = 1;
            $orderItem->unit_price = $price;
            $orderItem->currency = $order->currency;
            $orderItem->subtotal = $price;
            $orderItem->total = $price;
            $orderItem->save();

            $newTotal = $order->items()->sum('total');
            $order->subtotal = $newTotal;
            $order->total = $newTotal;
            $order->save();

            return $order;
        });
    }
}
