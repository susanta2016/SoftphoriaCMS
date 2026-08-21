<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Commerce\Models\Entitlement;

/**
 * Returned once by IssueEntitlementForOrderItemAction. plainGuestToken is
 * the only time the raw guest access token ever exists outside memory — the
 * Entitlement itself only stores its SHA-256 hash (access_token_hash). Null
 * for a registered purchaser (no token needed; they authenticate via
 * session in the future download endpoint).
 */
final readonly class IssuedEntitlement
{
    public function __construct(
        public Entitlement $entitlement,
        public ?string $plainGuestToken,
    ) {}
}
