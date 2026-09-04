<?php

namespace Tests\Feature\Registration;

use App\Enums\GratitudeJournalVisibility;
use App\Enums\UserStatus;
use App\Models\EmailVerification;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Services\Settings\SettingsRepository;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Free registration (point 2 of the confirmed spec) — validation, account
 * creation (PendingVerification, never Active on submission alone),
 * the verification token's storage shape (hashed, single active row,
 * 24h TTL), and the admin-configurable confirmation message.
 */
class FreeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_register_page_shows_the_live_global_pricing_value(): void
    {
        app(SettingsRepository::class)->set('pricing', 'pro_member_monthly_price', '12.34');

        $response = $this->get(route('register.show'));

        $response->assertOk();
        $response->assertSee('12.34');
        $response->assertSee('Share My Light');
        $response->assertSee('Become a Pro Member');
    }

    /**
     * docs/development instructions for SEO.docx §5: Registration and its
     * "thank you" confirmation are transactional pages, never candidates
     * for search results or the sitemap.
     */
    public function test_the_register_and_thank_you_pages_are_marked_noindex(): void
    {
        $this->get(route('register.show'))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get(route('register.free.thank-you'))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_registering_free_creates_a_pending_verification_user_and_a_hashed_single_use_token(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $response = $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register.free.thank-you'));

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame(UserStatus::PendingVerification->value, $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password123', $user->password));

        $verification = EmailVerification::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(64, strlen($verification->token));
        $this->assertTrue(ctype_xdigit($verification->token));
        $this->assertTrue($verification->expires_at->between(now()->addHours(23), now()->addHours(25)));

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('jane@example.com'));
    }

    public function test_registering_free_no_longer_collects_phone_number_address_or_zip_code(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $response = $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.profile@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // A client submitting these anyway (e.g. a stale cached form)
            // must have them silently ignored, not saved to a profile.
            'phone_number' => '+44 7700 900001',
            'address' => '221B Baker Street',
            'zip_code' => 'NW1 6XE',
        ]);

        $response->assertRedirect(route('register.free.thank-you'));

        $user = User::query()->where('email', 'jane.profile@example.com')->firstOrFail();
        $this->assertNull($user->profile);
    }

    public function test_the_registration_page_no_longer_shows_a_biography_field(): void
    {
        $response = $this->get(route('register.show'));

        $response->assertOk();
        $response->assertDontSee('Biography');
        $response->assertDontSee('name="bio"', false);
    }

    public function test_the_registration_page_shows_the_leave_a_little_light_prompt(): void
    {
        $response = $this->get(route('register.show'));

        $response->assertOk();
        $response->assertSee('Leave a Little Light');
        $response->assertSee('What words of light would you like to share with the gathering?');
        $response->assertSee('This Light Post will be shared publicly.');
        $response->assertSee('Leave a little light...');
        $response->assertSee('Share My Light');
        $response->assertSee('Share Another Time');
    }

    public function test_sharing_my_light_creates_a_public_light_post_for_the_new_user(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $response = $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.light@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'light_post_action' => 'share',
            'light_message' => 'Grateful for this space.',
        ]);

        $response->assertRedirect(route('register.free.thank-you'));

        $user = User::query()->where('email', 'jane.light@example.com')->firstOrFail();
        $post = $user->lightPosts()->firstOrFail();
        $this->assertSame('Grateful for this space.', $post->content);
        $this->assertSame(GratitudeJournalVisibility::Public, $post->visibility);
    }

    public function test_sharing_another_time_creates_no_light_post_even_with_text_typed(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.skip@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'light_post_action' => 'skip',
            'light_message' => 'Typed but not shared.',
        ]);

        $user = User::query()->where('email', 'jane.skip@example.com')->firstOrFail();
        $this->assertSame(0, $user->lightPosts()->count());
    }

    public function test_sharing_my_light_with_a_blank_message_creates_no_light_post(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.blank@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'light_post_action' => 'share',
            'light_message' => '   ',
        ]);

        $user = User::query()->where('email', 'jane.blank@example.com')->firstOrFail();
        $this->assertSame(0, $user->lightPosts()->count());
    }

    public function test_registering_free_without_any_light_post_choice_still_succeeds_and_creates_no_post(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $response = $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.nolight@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register.free.thank-you'));

        $user = User::query()->where('email', 'jane.nolight@example.com')->firstOrFail();
        $this->assertSame(0, $user->lightPosts()->count());
    }

    public function test_a_light_message_longer_than_the_configured_limit_is_rejected(): void
    {
        config(['features.light_post_max_length' => 50]);

        $response = $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.toolong@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'light_post_action' => 'share',
            'light_message' => str_repeat('a', 51),
        ]);

        $response->assertSessionHasErrors('light_message');
        $this->assertSame(0, User::query()->where('email', 'jane.toolong@example.com')->count());
    }

    public function test_the_pro_membership_option_is_hidden_when_the_feature_flag_is_disabled(): void
    {
        config(['features.member_subscription_enabled' => false]);

        $response = $this->get(route('register.show'));

        $response->assertOk();
        $response->assertDontSee('Become a Pro Member');
    }

    public function test_the_pro_membership_option_is_shown_when_the_feature_flag_is_enabled(): void
    {
        config(['features.member_subscription_enabled' => true]);

        $response = $this->get(route('register.show'));

        $response->assertOk();
        $response->assertSee('Become a Pro Member');
    }

    public function test_registering_free_with_a_duplicate_email_is_rejected_and_creates_no_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post(route('register.free'), [
            'name' => 'Someone Else',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register.show'));
        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::query()->where('email', 'taken@example.com')->count());
    }

    public function test_registering_free_with_an_invalid_email_format_is_rejected(): void
    {
        $response = $this->post(route('register.free'), [
            'name' => 'Someone',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, User::query()->count());
    }

    public function test_registering_free_with_a_mismatched_password_confirmation_is_rejected(): void
    {
        $response = $this->post(route('register.free'), [
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertSame(0, User::query()->count());
    }

    public function test_the_free_thank_you_page_shows_the_configured_message(): void
    {
        app(SettingsRepository::class)
            ->set('registration', 'free_confirmation_message', 'A custom free confirmation message.');

        $response = $this->get(route('register.free.thank-you'));

        $response->assertOk();
        $response->assertSee('A custom free confirmation message.');
    }

    public function test_the_free_thank_you_page_falls_back_to_the_suggested_default_message(): void
    {
        $response = $this->get(route('register.free.thank-you'));

        $response->assertOk();
        $response->assertSee('Thank you for your registration! Please verify your registered email from your mailbox.');
    }

    public function test_a_filled_honeypot_field_silently_discards_the_submission(): void
    {
        Mail::fake();

        $response = $this->post(route('register.free'), [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'hp_website' => 'https://spam.example.com',
        ]);

        // The bot gets the same redirect a genuine free registration
        // would get — no signal it was caught.
        $response->assertRedirect(route('register.free.thank-you'));
        $this->assertSame(0, User::query()->where('email', 'bot@example.com')->count());
        Mail::assertNothingSent();
    }

    public function test_registration_free_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('register.free'), [
                'name' => 'Someone',
                'email' => "rate{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        $response = $this->post(route('register.free'), [
            'name' => 'Someone',
            'email' => 'rate-blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429);
    }
}
