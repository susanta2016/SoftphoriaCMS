<?php

namespace App\Modules\Commerce\Actions\Entitlement;

use App\Modules\Commerce\Models\Entitlement;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Services\DownloadPolicy\DownloadPolicyResolver;
use App\Modules\Commerce\Support\IssuedEntitlement;

/**
 * Turns one paid OrderItem into the Entitlement that actually grants access
 * (§3/§10 of the approved brief) — called by MarkOrderPaidAction, never by
 * anything else. Guest orders get a random 32-byte token whose SHA-256 hash
 * is the only copy ever persisted (§10: "do NOT rely on predictable IDs...
 * opaque, high-entropy, cryptographically secure token"). max_downloads/
 * expires_at are snapshotted from DownloadPolicyResolver at issuance — an
 * admin changing the policy afterward never alters an already-issued grant.
 */
class IssueEntitlementForOrderItemAction
{
    public function __construct(private readonly DownloadPolicyResolver $policyResolver) {}

    public function handle(OrderItem $orderItem): IssuedEntitlement
    {
        $order = $orderItem->order;
        $isGuest = $order->isGuest();
        $policy = $isGuest ? $this->policyResolver->forGuest() : $this->policyResolver->forRegisteredMember();

        $plainToken = null;
        $tokenHash = null;

        if ($isGuest) {
            $plainToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $plainToken);
        }

        $entitlement = new Entitlement;
        $entitlement->order_item_id = $orderItem->getKey();
        $entitlement->user_id = $order->user_id;
        $entitlement->purchaser_email = $order->purchaser_email;
        $entitlement->album_id = $orderItem->album_id;
        $entitlement->single_id = $orderItem->single_id;
        $entitlement->access_token_hash = $tokenHash;
        $entitlement->max_downloads = $policy->maxDownloads;
        $entitlement->downloads_used = 0;
        $entitlement->expires_at = $policy->expiresAt();
        $entitlement->save();

        return new IssuedEntitlement($entitlement, $plainToken);
    }
}
