<?php

namespace Tests\Feature\Marketing;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Role;
use App\Models\User;
use App\Modules\Commerce\Services\Stripe\FakeStripeGateway;
use App\Modules\Commerce\Services\Stripe\StripeGatewayContract;
use App\Shared\Support\Marketing\UtmAttribution;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * First-touch UTM capture (CaptureUtmAttribution → session) and its
 * one-time copy onto the new User at Free/Pro registration, plus the admin
 * Users screens that report it.
 */
class UtmRegistrationAttributionTest extends TestCase
{
    use RefreshDatabase;

    private const string INSTAGRAM = '?utm_source=instagram&utm_medium=social&utm_campaign=launch&utm_content=bio';

    private const string EMAIL = '?utm_source=email&utm_medium=email&utm_campaign=followup';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $this->app->singleton(StripeGatewayContract::class, FakeStripeGateway::class);
    }

    // --------------------------------------------------------------- Capture

    public function test_a_visitor_arriving_with_utm_parameters_has_their_attribution_stored(): void
    {
        $this->get('/register'.self::INSTAGRAM)->assertOk();

        $stored = session(UtmAttribution::SESSION_KEY);

        $this->assertSame('instagram', $stored['utm_source']);
        $this->assertSame('social', $stored['utm_medium']);
        $this->assertSame('launch', $stored['utm_campaign']);
        $this->assertSame('bio', $stored['utm_content']);
        $this->assertArrayNotHasKey('utm_term', $stored);
        $this->assertStringEndsWith('/register'.self::INSTAGRAM, $stored['utm_landing_url']);
        $this->assertNotEmpty($stored['utm_captured_at']);
    }

    public function test_attribution_survives_browsing_other_pages(): void
    {
        $this->get('/register'.self::INSTAGRAM);
        $this->get('/register/free/thank-you');
        $this->get('/register');

        $this->assertSame('instagram', session(UtmAttribution::SESSION_KEY)['utm_source']);
    }

    public function test_a_visitor_without_utm_parameters_gets_no_attribution(): void
    {
        $this->get('/register?ref=music')->assertOk();

        $this->assertFalse(session()->has(UtmAttribution::SESSION_KEY));
    }

    public function test_unusable_utm_values_are_ignored_and_long_values_are_capped(): void
    {
        $this->get('/register?utm_source[]=x&utm_medium=%20%20&utm_campaign='.str_repeat('a', 300));

        $stored = session(UtmAttribution::SESSION_KEY);

        $this->assertArrayNotHasKey('utm_source', $stored);
        $this->assertArrayNotHasKey('utm_medium', $stored);
        $this->assertSame(100, mb_strlen($stored['utm_campaign']));
    }

    public function test_no_ip_address_or_user_agent_is_stored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'TestBrowser/1.0'])
            ->get('/register'.self::INSTAGRAM);

        $this->assertSame(
            ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_landing_url', 'utm_captured_at'],
            array_keys(session(UtmAttribution::SESSION_KEY)),
        );

        $serialized = json_encode(session(UtmAttribution::SESSION_KEY));
        $this->assertStringNotContainsString('203.0.113.9', $serialized);
        $this->assertStringNotContainsString('TestBrowser', $serialized);
    }

    public function test_attribution_from_any_public_landing_page_survives_until_registration_without_utm_in_the_url(): void
    {
        $this->get('/music?utm_source=instagram&utm_medium=social&utm_campaign=october_launch');
        $this->get('/podcast');
        $this->get('/inspirational-resources');
        $this->get('/');
        $this->get('/register')->assertOk();

        $this->registerFree('journey@example.com')->assertRedirect(route('register.free.thank-you'));

        $user = User::query()->where('email', 'journey@example.com')->sole();

        $this->assertSame('instagram', $user->utm_source);
        $this->assertSame('social', $user->utm_medium);
        $this->assertSame('october_launch', $user->utm_campaign);
        $this->assertStringEndsWith('/music?utm_source=instagram&utm_medium=social&utm_campaign=october_launch', $user->utm_landing_url);
    }

    public function test_a_link_to_a_missing_track_still_records_the_attribution(): void
    {
        $this->get('/music/tracks/no-such-track?utm_source=podcast&utm_medium=podcast&utm_campaign=ep12')->assertNotFound();

        $this->assertSame('podcast', session(UtmAttribution::SESSION_KEY)['utm_source']);
    }

    // ------------------------------------------------------------ First touch

    public function test_a_later_utm_link_never_overwrites_the_first_touch(): void
    {
        $this->get('/register'.self::INSTAGRAM);
        $this->get('/register'.self::EMAIL);

        $this->registerFree('first.touch@example.com');

        $user = User::query()->where('email', 'first.touch@example.com')->sole();

        $this->assertSame('instagram', $user->utm_source);
        $this->assertSame('social', $user->utm_medium);
        $this->assertSame('launch', $user->utm_campaign);
    }

    // ----------------------------------------------------------- Registration

    public function test_free_registration_stores_the_attribution_and_clears_the_session(): void
    {
        $this->get('/register'.self::INSTAGRAM);

        $this->registerFree('free.utm@example.com')->assertRedirect(route('register.free.thank-you'));

        $user = User::query()->where('email', 'free.utm@example.com')->sole();

        $this->assertSame('instagram', $user->utm_source);
        $this->assertSame('social', $user->utm_medium);
        $this->assertSame('launch', $user->utm_campaign);
        $this->assertSame('bio', $user->utm_content);
        $this->assertNull($user->utm_term);
        $this->assertStringEndsWith('/register'.self::INSTAGRAM, $user->utm_landing_url);
        $this->assertNotNull($user->utm_captured_at);
        $this->assertFalse(session()->has(UtmAttribution::SESSION_KEY));
    }

    public function test_pro_registration_stores_the_attribution(): void
    {
        $this->get('/register?utm_source=apple_music&utm_medium=music&utm_campaign=album_drop');

        $this->post(route('register.pro'), $this->registrationData('pro.utm@example.com', 'pro_utm'))->assertOk();

        $user = User::query()->where('email', 'pro.utm@example.com')->sole();

        $this->assertSame('apple_music', $user->utm_source);
        $this->assertSame('music', $user->utm_medium);
        $this->assertSame('album_drop', $user->utm_campaign);
    }

    public function test_registration_without_utm_parameters_still_works_and_leaves_every_field_null(): void
    {
        $this->get('/register');

        $this->registerFree('plain@example.com')->assertRedirect(route('register.free.thank-you'));

        $user = User::query()->where('email', 'plain@example.com')->sole();

        $this->assertSame(UserStatus::PendingVerification->value, $user->status);

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_landing_url', 'utm_captured_at'] as $column) {
            $this->assertNull($user->{$column}, $column);
        }
    }

    public function test_a_failed_registration_keeps_the_attribution_for_the_next_attempt(): void
    {
        $this->get('/register'.self::INSTAGRAM);

        $this->post(route('register.free'), [...$this->registrationData('retry@example.com', 'retry_user'), 'password_confirmation' => 'mismatch'])
            ->assertSessionHasErrors('password');

        $this->registerFree('retry@example.com', 'retry_user');

        $this->assertSame('instagram', User::query()->where('email', 'retry@example.com')->sole()->utm_source);
    }

    // --------------------------------------------------------- Existing users

    public function test_a_signed_in_user_visiting_a_utm_link_is_never_attributed_or_changed(): void
    {
        $member = User::factory()->create(['status' => UserStatus::Active->value, 'utm_source' => 'podcast', 'utm_medium' => 'podcast', 'utm_campaign' => 'ep1']);
        $untracked = User::factory()->create(['status' => UserStatus::Active->value]);

        $this->actingAs($member)->get('/register'.self::EMAIL);
        $this->assertFalse(session()->has(UtmAttribution::SESSION_KEY));

        $this->actingAs($untracked)->get('/register'.self::EMAIL);
        $this->assertFalse(session()->has(UtmAttribution::SESSION_KEY));

        $this->assertSame('podcast', $member->refresh()->utm_source);
        $this->assertSame('ep1', $member->utm_campaign);
        $this->assertNull($untracked->refresh()->utm_source);
    }

    public function test_a_resumed_abandoned_pro_registration_keeps_its_original_attribution(): void
    {
        $existing = User::factory()->create([
            'email' => 'abandoned@example.com',
            'status' => UserStatus::PendingVerification->value,
            'utm_source' => 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => 'first_visit',
        ]);

        $this->get('/register'.self::EMAIL);
        $this->post(route('register.pro'), $this->registrationData('abandoned@example.com', 'abandoned_user'))->assertOk();

        $existing->refresh();
        $this->assertSame('facebook', $existing->utm_source);
        $this->assertSame('first_visit', $existing->utm_campaign);
    }

    public function test_the_migration_leaves_existing_users_without_attribution(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->refresh()->utm_source);
        $this->assertNull($user->utm_captured_at);
    }

    // ----------------------------------------------------------------- Admin

    public function test_the_admin_user_view_shows_the_acquisition_attribution(): void
    {
        $user = User::factory()->create([
            'utm_source' => 'instagram',
            'utm_medium' => 'social',
            'utm_campaign' => 'summer_launch',
            'utm_content' => 'bio',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ViewUser::class, ['record' => $user->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Acquisition')
            ->assertSee('Instagram')
            ->assertSee('summer_launch')
            ->assertSee('bio');
    }

    public function test_admin_can_filter_users_by_source_medium_and_campaign(): void
    {
        $admin = $this->admin();
        $instagram = User::factory()->create(['utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => 'launch']);
        $facebook = User::factory()->create(['utm_source' => 'facebook', 'utm_medium' => 'social', 'utm_campaign' => 'followup']);
        $email = User::factory()->create(['utm_source' => 'email', 'utm_medium' => 'email', 'utm_campaign' => 'launch']);
        $none = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableColumnFormattedStateSet('utm_source', 'Instagram', $instagram)
            ->filterTable('utm_source', 'instagram')
            ->assertCanSeeTableRecords([$instagram])
            ->assertCanNotSeeTableRecords([$facebook, $email, $none])
            ->resetTableFilters()
            ->filterTable('utm_medium', 'social')
            ->assertCanSeeTableRecords([$instagram, $facebook])
            ->assertCanNotSeeTableRecords([$email, $none])
            ->resetTableFilters()
            ->filterTable('utm_campaign', 'launch')
            ->assertCanSeeTableRecords([$instagram, $email])
            ->assertCanNotSeeTableRecords([$facebook, $none]);
    }

    private function registerFree(string $email, string $username = 'utm_member'): TestResponse
    {
        return $this->post(route('register.free'), $this->registrationData($email, $username));
    }

    /**
     * @return array<string, string>
     */
    private function registrationData(string $email, string $username): array
    {
        return [
            'name' => 'UTM Member',
            'username' => $username,
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        return $user;
    }
}
