<?php

namespace App\Modules\Commerce\Support;

/**
 * Returned by CheckAlbumReadinessAction/CheckSingleReadinessAction — the one
 * shape used by every caller (Filament's readiness panel,
 * CreatePendingOrderAction, and any future checkout frontend), per §15/§9 of
 * the approved brief: "reusable by Admin UI, future frontend, future
 * checkout/order validation. Do not duplicate this logic in multiple
 * places."
 */
final readonly class PurchaseReadinessResult
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(
        public bool $ready,
        public array $issues,
    ) {}

    public static function ready(): self
    {
        return new self(true, []);
    }

    /**
     * @param  array<int, string>  $issues
     */
    public static function notReady(array $issues): self
    {
        return new self(false, $issues);
    }
}
