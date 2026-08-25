<?php

namespace Tests\Feature\Registration;

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
        $response->assertSee('Register Free');
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
            ...$this->requiredProfileFields(),
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

    public function test_registering_free_with_profile_fields_saves_a_profile(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $response = $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.profile@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '+44 7700 900001',
            'bio' => 'Writes things.',
            'address' => '221B Baker Street',
            'zip_code' => 'NW1 6XE',
        ]);

        $response->assertRedirect(route('register.free.thank-you'));

        $user = User::query()->where('email', 'jane.profile@example.com')->firstOrFail();
        $this->assertSame('+44 7700 900001', $user->profile->phone_number);
        $this->assertSame('Writes things.', $user->profile->bio);
        $this->assertSame('221B Baker Street', $user->profile->address);
        $this->assertSame('NW1 6XE', $user->profile->zip_code);
    }

    public function test_registering_free_without_a_biography_still_succeeds(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $response = $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.nobio@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$this->requiredProfileFields(),
        ]);

        $response->assertRedirect(route('register.free.thank-you'));

        $user = User::query()->where('email', 'jane.nobio@example.com')->firstOrFail();
        $this->assertNull($user->profile->bio);
    }

    public function test_registering_free_requires_phone_number_address_and_zip_code(): void
    {
        $response = $this->post(route('register.free'), [
            'name' => 'Jane Doe',
            'email' => 'jane.missing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['phone_number', 'address', 'zip_code']);
        $this->assertSame(0, User::query()->where('email', 'jane.missing@example.com')->count());
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

    /**
     * @return array{phone_number: string, address: string, zip_code: string}
     */
    private function requiredProfileFields(): array
    {
        return [
            'phone_number' => '+44 7700 900001',
            'address' => '221B Baker Street',
            'zip_code' => 'NW1 6XE',
        ];
    }
}
