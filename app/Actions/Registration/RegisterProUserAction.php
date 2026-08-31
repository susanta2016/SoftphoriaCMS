<?php

namespace App\Actions\Registration;

use App\Actions\Registration\Concerns\CreatesLightPostOnRegistration;
use App\Actions\Registration\Concerns\SavesOptionalRegistrationProfile;
use App\Actions\Registration\Support\ProRegistrationOutcome;
use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Commerce\Services\Pricing\GlobalPricingResolver;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Pro registration (point 3/4/9 of the confirmed spec). Price is always
 * resolved here, server-side, from GlobalPricingResolver — never accepted
 * from the request. This never writes to the `subscriptions` table itself;
 * that stays exclusively the webhook's job
 * (HandleCheckoutSessionCompletedAction), preserving the existing Stripe
 * webhook synchronization as the sole source of truth.
 *
 * Abandoned-registration handling (point on "Pro retry"): an email that
 * already belongs to a PendingVerification user with no active
 * subscription is a legitimate retry, not a duplicate — the existing user
 * row is reused as-is (name/password from this submission are discarded,
 * not applied, since silently overwriting a stranger's password via a
 * "retry" would be an account-takeover vector) and a fresh Checkout Session
 * is issued. An email already fully registered (Active), or already paid
 * but still awaiting email verification, is not offered a new Checkout
 * Session — see ProRegistrationOutcome.
 */
class RegisterProUserAction
{
    use CreatesLightPostOnRegistration;
    use SavesOptionalRegistrationProfile;

    public function __construct(
        private readonly GlobalPricingResolver $pricing,
        private readonly StripeGatewayContract $stripe,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, phone_number?: ?string, address?: ?string, zip_code?: ?string, light_post_action?: ?string, light_message?: ?string}  $data
     */
    public function handle(array $data): ProRegistrationOutcome
    {
        $existing = User::query()->where('email', $data['email'])->first();

        if ($existing !== null) {
            if ($existing->status !== UserStatus::PendingVerification->value) {
                throw ValidationException::withMessages([
                    'email' => 'This email address is already registered.',
                ]);
            }

            if ($existing->hasActiveMembership()) {
                return ProRegistrationOutcome::alreadyPaidAwaitingVerification($existing);
            }

            // Same account-takeover reasoning as name/password above: this
            // submission's profile fields are discarded, not merged into a
            // stranger's existing profile, on a resumed abandoned attempt.
            $user = $existing;
        } else {
            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = Hash::make($data['password']);
            $user->status = UserStatus::PendingVerification->value;
            $user->save();

            $this->saveOptionalProfile($user, $data);
            $this->createLightPostIfRequested($user, $data);
        }

        $clientSecret = $this->stripe->createEmbeddedSubscriptionCheckoutSession(
            $user,
            $this->pricing->proMemberMonthlyPrice(),
            $this->buildReturnUrl($user),
        );

        return ProRegistrationOutcome::checkoutReady($user, $clientSecret);
    }

    /**
     * Signed so the completion page can trust the `user` route parameter
     * without a login system — tampering with it invalidates the
     * signature. `session_id`/`attempt` are appended *after* signing (Stripe
     * substitutes `{CHECKOUT_SESSION_ID}` itself; `attempt` powers the
     * completion page's own refresh-retry loop), so both must be ignored
     * during validation — see RegistrationController::proComplete().
     */
    private function buildReturnUrl(User $user): string
    {
        $signed = URL::temporarySignedRoute('register.pro.complete', now()->addHours(2), ['user' => $user->getKey()]);

        return $signed.(str_contains($signed, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}';
    }
}
