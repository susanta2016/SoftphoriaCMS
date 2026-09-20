<?php

namespace Tests\Feature\Account;

use App\Enums\UserStatus;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * AUTH-005 — account profile. Private/member-only content per the
 * platform's standing indexing rule: real auth (never robots.txt),
 * noindex, and there is no user-id route parameter to forge (edits
 * Auth::user() only), so a different member can never reach another
 * account's profile by guessing a URL.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/account/profile');

        $response->assertRedirect(route('login'));
    }

    public function test_the_owning_member_can_view_their_profile(): void
    {
        $user = User::factory()->create(['status' => 'active', 'name' => 'Jane Visitor']);

        $response = $this->actingAs($user)->get('/account/profile');

        $response->assertOk();
        $response->assertSee('Jane Visitor');
    }

    public function test_the_page_is_noindexed(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/account/profile');

        $response->assertSee('noindex', false);
    }

    public function test_a_suspended_users_session_is_rejected(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);

        $response = $this->actingAs($user)->get('/account/profile');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_pending_verification_user_can_still_access_their_account(): void
    {
        $user = User::factory()->create(['status' => UserStatus::PendingVerification->value, 'email_verified_at' => null]);

        $response = $this->actingAs($user)->get('/account/profile');

        $response->assertOk();
    }

    public function test_owner_can_update_name_and_bio(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->patch('/account/profile', [
            'name' => 'Updated Name',
            'email' => $user->email,
            'bio' => 'Hello world.',
        ]);

        $response->assertRedirect(route('account.profile.edit'));
        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('Hello world.', $user->fresh()->profile->bio);
    }

    public function test_changing_email_resets_verification_and_sends_a_new_link(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->patch('/account/profile', [
            'name' => $user->name,
            'email' => 'changed@example.com',
        ]);

        $fresh = $user->fresh();
        $this->assertSame('changed@example.com', $fresh->email);
        $this->assertSame(UserStatus::PendingVerification->value, $fresh->status);
        $this->assertNull($fresh->email_verified_at);

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail): bool {
            return $mail->hasTo('changed@example.com');
        });
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->from('/account/profile')->patch('/account/profile', [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
