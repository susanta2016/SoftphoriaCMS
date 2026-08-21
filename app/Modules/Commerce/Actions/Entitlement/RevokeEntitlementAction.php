<?php

namespace App\Modules\Commerce\Actions\Entitlement;

use App\Models\User;
use App\Modules\Commerce\Models\Entitlement;
use App\Shared\Services\AuditLogService;

/**
 * The only way an Entitlement is revoked (§3/§16: "if an entitlement can be
 * revoked, design that safely") — always an explicit admin action from
 * OrderResource/EntitlementResource, never automatic. Sets revoked_at rather
 * than deleting the row, so the grant's full history (what was purchased,
 * when, by whom, and why it was later revoked) stays intact for support/
 * audit. Recorded via the existing AuditLogService, same pattern
 * GlobalPricing::save() already uses.
 */
class RevokeEntitlementAction
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function handle(Entitlement $entitlement, User $admin, ?string $reason = null): void
    {
        $entitlement->revoked_at = now();
        $entitlement->revoked_reason = $reason;
        $entitlement->revoked_by = $admin->getKey();
        $entitlement->save();

        $this->auditLog->record($admin, 'entitlement.revoked', $entitlement, ['reason' => $reason]);
    }
}
