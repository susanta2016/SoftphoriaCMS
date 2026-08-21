<?php

namespace App\Modules\Commerce\Support;

/**
 * A verified, parsed Stripe webhook event — the only shape
 * StripeWebhookController and the Handle*Action classes ever see. Keeps
 * every consumer ignorant of the Stripe SDK's own Event object, which is
 * what lets FakeStripeGateway stand in for it in tests without depending on
 * stripe-php's internals.
 */
final readonly class StripeEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $data,
    ) {}
}
