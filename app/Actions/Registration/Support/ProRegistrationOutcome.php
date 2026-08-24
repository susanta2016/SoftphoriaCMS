<?php

namespace App\Actions\Registration\Support;

use App\Models\User;

/**
 * What RegisterProUserAction produced — three distinct outcomes the
 * controller renders differently. `checkoutReady` covers both a brand-new
 * signup and a legitimate abandoned-payment retry (same user row reused);
 * `alreadyPaidAwaitingVerification` is the one case where a Checkout
 * Session is deliberately NOT created again, per the confirmed
 * abandoned-registration rule.
 */
final readonly class ProRegistrationOutcome
{
    private function __construct(
        public User $user,
        public ?string $clientSecret,
        public bool $alreadyPaidAwaitingVerification,
    ) {}

    public static function checkoutReady(User $user, string $clientSecret): self
    {
        return new self($user, $clientSecret, false);
    }

    public static function alreadyPaidAwaitingVerification(User $user): self
    {
        return new self($user, null, true);
    }
}
