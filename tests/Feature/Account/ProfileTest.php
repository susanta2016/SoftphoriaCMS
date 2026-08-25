<?php

namespace Tests\Feature\Account;

use App\Enums\UserStatus;
use App\Models\EmailVerification;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Account profile edit (points 3/6/9 of the confirmed spec) — own-data-only
 * updates, email uniqueness ignoring self, mass-assignment protection of
 * id/status/roles, and email-change re-triggering verification exactly like
 * registration does.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_denied(): void
    {
        $response = $this->get(route('account.profile.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_owner_can_update_their_own_name_and_profile_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => 'New Name',
            'email' => $user->email,
            'phone_number' => '+44 7700 900002',
            'bio' => 'Updated bio.',
            'address' => '10 Downing Street',
            'zip_code' => 'SW1A 2AA',
        ]);

        $response->assertRedirect(route('account.profile.edit'));

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('+44 7700 900002', $user->profile->phone_number);
        $this->assertSame('Updated bio.', $user->profile->bio);
    }

    public function test_email_uniqueness_ignores_the_current_user(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $response = $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => $user->name,
            'email' => 'me@example.com',
        ]);

        $response->assertRedirect(route('account.profile.edit'));
        $response->assertSessionDoesntHaveErrors('email');
    }

    public function test_email_uniqueness_rejects_another_users_address(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertNotSame('taken@example.com', $user->fresh()->email);
    }

    public function test_changing_the_email_flips_status_to_pending_verification_and_sends_a_new_verification_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $user = User::factory()->create(['email' => 'old@example.com', 'status' => UserStatus::Active->value]);

        $response = $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => $user->name,
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect(route('account.profile.edit'));

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertSame(UserStatus::PendingVerification->value, $user->status);
        $this->assertNull($user->email_verified_at);

        $this->assertSame(1, EmailVerification::query()->where('user_id', $user->id)->count());
        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo('new@example.com'));
    }

    public function test_keeping_the_same_email_does_not_touch_verification_state(): void
    {
        Mail::fake();
        $user = User::factory()->create(['status' => UserStatus::Active->value]);
        $verifiedAt = $user->email_verified_at;

        $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => 'Still Me',
            'email' => $user->email,
        ]);

        $user->refresh();
        $this->assertSame(UserStatus::Active->value, $user->status);
        $this->assertEquals($verifiedAt, $user->email_verified_at);
        Mail::assertNothingSent();
    }

    public function test_posting_protected_fields_is_silently_ignored(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active->value]);
        $originalId = $user->id;

        $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'id' => $originalId + 999,
            'status' => 'banned',
        ]);

        $user->refresh();
        $this->assertSame($originalId, $user->id);
        $this->assertSame(UserStatus::Active->value, $user->status);
    }

    public function test_a_user_cannot_alter_another_users_profile(): void
    {
        $userA = User::factory()->create(['name' => 'User A']);
        $userB = User::factory()->create(['name' => 'User B']);

        $this->actingAs($userB)->patch(route('account.profile.update'), [
            'name' => 'Hijacked Name',
            'email' => $userB->email,
        ]);

        $this->assertSame('User A', $userA->fresh()->name);
        $this->assertSame('Hijacked Name', $userB->fresh()->name);
    }

    public function test_the_profile_page_is_marked_noindex(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.profile.edit'));

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
