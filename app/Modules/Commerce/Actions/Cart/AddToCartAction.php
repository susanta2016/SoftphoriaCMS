<?php

namespace App\Modules\Commerce\Actions\Cart;

use App\Modules\Commerce\Actions\PurchaseReadiness\CheckAlbumReadinessAction;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckSingleReadinessAction;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckTrackReadinessAction;
use App\Modules\Commerce\Exceptions\PurchaseNotReadyException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Services\Pricing\GlobalPricingResolver;
use App\Modules\Commerce\Support\PurchaseReadinessResult;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
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
        private readonly CheckTrackReadinessAction $checkTrack,
        private readonly GlobalPricingResolver $pricing,
    ) {}

    /**
     * @throws PurchaseNotReadyException
     */
    public function handle(Order $order, Album|Single|Track $item): Order
    {
        $column = $this->columnFor($item);

        $alreadyInCart = $order->items()->where($column, $item->getKey())->exists();

        if ($alreadyInCart) {
            return $order;
        }

        $readiness = $this->checkReadiness($item);

        if (! $readiness->ready) {
            throw PurchaseNotReadyException::forIssues($item->title, $readiness->issues);
        }

        $price = $item instanceof Album ? $this->pricing->fullAlbumPrice() : $this->pricing->perSongPrice();

        return DB::transaction(function () use ($order, $item, $column, $price): Order {
            $orderItem = new OrderItem;
            $orderItem->order_id = $order->getKey();
            $orderItem->{$column} = $item->getKey();
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

    private function columnFor(Album|Single|Track $item): string
    {
        return match (true) {
            $item instanceof Album => 'album_id',
            $item instanceof Single => 'single_id',
            default => 'track_id',
        };
    }

    private function checkReadiness(Album|Single|Track $item): PurchaseReadinessResult
    {
        return match (true) {
            $item instanceof Album => $this->checkAlbum->handle($item),
            $item instanceof Single => $this->checkSingle->handle($item),
            default => $this->checkTrack->handle($item),
        };
    }
}
