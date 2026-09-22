<?php

namespace Tests\Feature\Registration;

use App\Enums\GratitudeJournalVisibility;
use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Commerce\Services\Stripe\FakeStripeGateway;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pro registration (points 3/4/9/"Pro retry" of the confirmed spec) —
 * server-side price resolution (never trusting the client), the Stripe
 * Embedded Checkout Session creation, and the abandoned-registration
 * reuse/duplicate-prevention rules. Payment confirmation itself (webhook →
 * Subscription active → welcome email) is covered by
 * tests/Feature/Commerce/ProSubscriptionCheckoutWebhookTest.php — this file
 * only covers what happens *before* Stripe confirms anything.
 */
class ProRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(StripeGatewayContract::class, FakeStripeGateway::class);
    }

    public function test_registering_pro_creates_a_pending_verification_user_and_an_embedded_checkout_session(): void
    {
        $response = $this->post(route('register.pro'), [
            'name' => 'Jane Pro',
            'username' => 'jane_pro',
            'email' => 'jane.pro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertOk();

        $user = User::query()->where('email', 'jane.pro@example.com')->firstOrFail();
        $this->assertSame(UserStatus::PendingVerification->value, $user->status);
        $this->assertSame('jane_pro', $user->username);
        $this->assertNull(Subscription::query()->where('user_id', $user->id)->first());

        /** @var FakeStripeGateway $fake */
        $fake = app(StripeGatewayContract::class);
        $this->assertCount(1, $fake->subscriptionSessionsCreated);
        $this->assertSame($user->id, $fake->subscriptionSessionsCreated[0]['user']->id);
    }

    /**
     * docs/development instructions for SEO.docx §5: a Stripe Checkout
     * page is never a candidate for search results or the sitemap.
     */
    public function test_the_pro_checkout_page_is_marked_noindex(): void
    {
        $response = $this->post(route('register.pro'), [
            'name' => 'Jane Pro',
            'username' => 'jane_pro_noindex',
            'email' => 'jane.pro.noindex@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_registering_pro_no_longer_collects_phone_number_address_or_zip_code(): void
    {
        $this->post(route('register.pro'), [
            'name' => 'Jane Pro',
            'username' => 'jane_pro_profile',
            'email' => 'jane.pro.profile@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // A client submitting these anyway (e.g. a stale cached form)
            // must have them silently ignored, not saved to a profile.
            'phone_number' => '+44 7700 900001',
            'address' => '221B Baker Street',
            'zip_code' => 'NW1 6XE',
        ]);

        $user = User::query()->where('email', 'jane.pro.profile@example.com')->firstOrFail();
        $this->assertNull($user->profile);
    }

    public function test_sharing_my_light_on_pro_registration_creates_a_public_light_post(): void
    {
        $this->post(route('register.pro'), [
            'name' => 'Jane Pro',
            'username' => 'jane_pro_light',
            'email' => 'jane.pro.light@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'light_post_action' => 'share',
            'light_message' => 'Sharing my light as a Pro member.',
        ]);

        $user = User::query()->where('email', 'jane.pro.light@example.com')->firstOrFail();
        $post = $user->lightPosts()->firstOrFail();
        $this->assertSame('Sharing my light as a Pro member.', $post->content);
        $this->assertSame(GratitudeJournalVisibility::Public, $post->visibility);
    }

    public function test_resuming_an_abandoned_pro_registration_does_not_create_a_second_light_post(): void
    {
        $this->post(route('register.pro'), [
            'name' => 'Retry Light',
            'username' => 'retry_light',
            'email' => 'retry.light@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'light_post_action' => 'share',
            'light_message' => 'Original light post.',
        ]);

        $this->post(route('register.pro'), [
            'name' => 'Retry Light Again',
            'username' => 'retry_light_again',
            'email' => 'retry.light@example.com',
            'password' => 'a-different-password',
            'password_confirmation' => 'a-different-password',
            'light_post_action' => 'share',
            'light_message' => 'A stranger-submitted light post.',
        ]);

        $user = User::query()->where('email', 'retry.light@example.com')->firstOrFail();
        $this->assertSame(1, $user->lightPosts()->count());
        $this->assertSame('Original light post.', $user->lightPosts()->first()->content);
    }

    public function test_the_server_resolves_the_current_global_pricing_value_never_the_client(): void
    {
        app(SettingsRepository::class)->set('pricing', 'pro_member_monthly_price', '19.99');

        // A malicious/naive client attempting to submit its own price —
        // must be silently ignored; the server always resolves its own.
        $this->post(route('register.pro'), [
            'name' => 'Jane Pro',
            'username' => 'jane_pro2',
            'email' => 'jane.pro2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'price' => '0.01',
            'pro_member_monthly_price' => '0.01',
        ]);

        /** @var FakeStripeGateway $fake */
        $fake = app(StripeGatewayContract::class);
        $this->assertSame('19.99', $fake->subscriptionSessionsCreated[0]['priceAmount']);
    }

    public function test_registering_pro_with_an_email_already_fully_active_is_rejected(): void
    {
        User::factory()->create(['email' => 'active@example.com', 'status' => UserStatus::Active->value]);

        $response = $this->post(route('register.pro'), [
            'name' => 'Someone',
            'username' => 'someone_active',
            'email' => 'active@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register.show'));
        $response->assertSessionHasErrors('email');

        /** @var FakeStripeGateway $fake */
        $fake = app(StripeGatewayContract::class);
        $this->assertCount(0, $fake->subscriptionSessionsCreated);
    }

    public function test_an_abandoned_pro_registration_can_be_resumed_without_creating_a_duplicate_user(): void
    {
        $first = $this->post(route('register.pro'), [
            'name' => 'Retry Me',
            'username' => 'retry_me',
            'email' => 'retry@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $first->assertOk();

        $userCountAfterFirstAttempt = User::query()->where('email', 'retry@example.com')->count();
        $this->assertSame(1, $userCountAfterFirstAttempt);

        // Abandoned: never completed payment, still PendingVerification, no
        // Subscription row. Submitting the form again must reuse the same
        // user, not create a second account. Deliberately resubmits the
        // *same* username as the first attempt — a legitimate retry
        // colliding with its own already-saved value must not be rejected
        // (UsernameRules::rules()'s $enforceUniqueness — see registerPro()).
        $second = $this->post(route('register.pro'), [
            'name' => 'Retry Me Again',
            'username' => 'retry_me',
            'email' => 'retry@example.com',
            'password' => 'a-different-password',
            'password_confirmation' => 'a-different-password',
        ]);
        $second->assertOk();

        $this->assertSame(1, User::query()->where('email', 'retry@example.com')->count());

        $user = User::query()->where('email', 'retry@example.com')->firstOrFail();
        // The original name/username/password must survive — a retry never
        // silently overwrites them (would otherwise be an account-takeover
        // vector).
        $this->assertSame('Retry Me', $user->name);
        $this->assertSame('retry_me', $user->username);

        /** @var FakeStripeGateway $fake */
        $fake = app(StripeGatewayContract::class);
        $this->assertCount(2, $fake->subscriptionSessionsCreated);
    }

    public function test_a_pro_user_who_already_paid_but_has_not_verified_is_not_offered_a_new_checkout_session(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'paid.pending@example.com',
            'status' => UserStatus::PendingVerification->value,
        ]);
        Subscription::query()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
        ]);

        $response = $this->post(route('register.pro'), [
            'name' => 'Paid Pending',
            'username' => 'paid_pending',
            'email' => 'paid.pending@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register.show'));
        $response->assertSessionHas('registration_notice');

        /** @var FakeStripeGateway $fake */
        $fake = app(StripeGatewayContract::class);
        $this->assertCount(0, $fake->subscriptionSessionsCreated);
    }

    public function test_a_filled_honeypot_field_silently_discards_the_submission(): void
    {
        // The bot gets the same redirect a genuine free registration would
        // get — no signal it was caught, and crucially no Stripe Checkout
        // Session is ever created for it.
        $response = $this->post(route('register.pro'), [
            'name' => 'Spam Bot',
            'username' => 'spam_bot_pro',
            'email' => 'bot.pro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'hp_website' => 'https://spam.example.com',
        ]);

        $response->assertRedirect(route('register.free.thank-you'));
        $this->assertSame(0, User::query()->where('email', 'bot.pro@example.com')->count());

        /** @var FakeStripeGateway $fake */
        $fake = app(StripeGatewayContract::class);
        $this->assertCount(0, $fake->subscriptionSessionsCreated);
    }

    public function test_registering_pro_without_a_username_is_rejected(): void
    {
        $response = $this->post(route('register.pro'), [
            'name' => 'No Username',
            'email' => 'no.username.pro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertSame(0, User::query()->where('email', 'no.username.pro@example.com')->count());

        /** @var FakeStripeGateway $fake */
        $fake = app(StripeGatewayContract::class);
        $this->assertCount(0, $fake->subscriptionSessionsCreated);
    }

    /**
     * Unlike a retry resubmitting its own value (see
     * test_an_abandoned_pro_registration_can_be_resumed_without_creating_a_duplicate_user
     * above), a genuinely new Pro registration colliding with a *different*
     * existing member's username must still be rejected — RegisterProUserAction's
     * own pre-save check (UsernameRules::isTaken()), since the controller's
     * validator deliberately skips its uniqueness rule for this route.
     */
    public function test_registering_pro_with_a_username_already_taken_by_a_different_user_is_rejected(): void
    {
        User::factory()->create(['username' => 'existing_member']);

        $response = $this->post(route('register.pro'), [
            'name' => 'Someone New',
            'username' => 'existing_member',
            'email' => 'someone.new.pro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register.show'));
        $response->assertSessionHasErrors('username');
        $this->assertSame(0, User::query()->where('email', 'someone.new.pro@example.com')->count());

        /** @var FakeStripeGateway $fake */
        $fake = app(StripeGatewayContract::class);
        $this->assertCount(0, $fake->subscriptionSessionsCreated);
    }

    public function test_registration_pro_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('register.pro'), [
                'name' => 'Someone',
                'username' => "prorate_user_{$i}",
                'email' => "prorate{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        $response = $this->post(route('register.pro'), [
            'name' => 'Someone',
            'username' => 'prorate_blocked',
            'email' => 'prorate-blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429);
    }
}
