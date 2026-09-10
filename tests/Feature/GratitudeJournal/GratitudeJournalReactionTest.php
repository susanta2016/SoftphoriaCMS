<?php

namespace Tests\Feature\GratitudeJournal;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Enums\GratitudeJournalVisibility;
use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 🙌 reaction on a Gratitude Journal shared-feed entry
 * (GratitudeJournalReactionController) — reuses the exact same generic
 * App\Models\Reaction / App\Actions\Reaction\ToggleReactionAction
 * architecture as Tests\Feature\Music\TrackReactionTest, scoped to
 * App\Models\LightPost rows where source = journal AND visibility = public
 * (simplified from three states to two, 2026-09-10 — the guard moved from
 * Community to Public). A registration-sourced Light Post and a Private
 * journal entry must never become reactable through this endpoint even when
 * targeted directly by public_id. The shared feed itself is open to guests
 * (client-confirmed, 2026-09-10) — reacting is the one thing on that page
 * that still requires an account, so a guest hitting this endpoint (whether
 * via the feed's own register-link markup or a direct POST) is redirected
 * to registration, not login — a first-time visitor wanting to react is
 * treated as a registration prospect, not a returning member.
 */
class GratitudeJournalReactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_react_and_is_redirected_to_register(): void
    {
        $entry = $this->publicEntry();

        $response = $this->post(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));

        $response->assertRedirect(route('register.show'));
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_an_authenticated_member_can_react(): void
    {
        $entry = $this->publicEntry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));

        $response->assertRedirect();
        $this->assertSame(1, Reaction::query()->count());
        $reaction = Reaction::query()->first();
        $this->assertSame($user->getKey(), $reaction->user_id);
        $this->assertSame($entry->getKey(), $reaction->reactable_id);
        $this->assertSame(LightPost::class, $reaction->reactable_type);
    }

    public function test_the_async_endpoint_returns_reacted_true_and_the_correct_count(): void
    {
        $entry = $this->publicEntry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));

        $response->assertOk();
        $response->assertJson(['reacted' => true, 'count' => 1]);
    }

    public function test_toggling_again_removes_the_reaction_and_reports_reacted_false(): void
    {
        $entry = $this->publicEntry();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));
        $response = $this->actingAs($user)->postJson(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));

        $response->assertOk();
        $response->assertJson(['reacted' => false, 'count' => 0]);
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_the_same_user_cannot_create_a_duplicate_reaction(): void
    {
        $entry = $this->publicEntry();
        $user = User::factory()->create();

        Reaction::query()->create([
            'reactable_type' => LightPost::class,
            'reactable_id' => $entry->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(QueryException::class);

        Reaction::query()->create([
            'reactable_type' => LightPost::class,
            'reactable_id' => $entry->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_two_different_members_can_each_react_to_the_same_entry(): void
    {
        $entry = $this->publicEntry();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)->post(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));
        $this->actingAs($userB)->post(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));

        $this->assertSame(2, Reaction::query()->count());
    }

    public function test_a_private_journal_entry_cannot_be_reacted_to(): void
    {
        $entry = $this->publicEntry(GratitudeJournalVisibility::Private);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));

        $response->assertNotFound();
        $this->assertSame(0, Reaction::query()->count());
    }

    /**
     * The key regression guard: LightPost is shared with registration-time
     * "Leave a Little Light" posts (source = registration) — this endpoint
     * must reject one outright, even though it shares the exact same
     * reactable_type string a Public journal entry would use.
     */
    public function test_a_registration_light_post_cannot_be_reacted_to(): void
    {
        $user = User::factory()->create();
        $registrationPost = LightPost::query()->create([
            'user_id' => $user->id,
            'source' => LightPostSource::Registration,
            'content' => 'A registration-time light post.',
            'visibility' => GratitudeJournalVisibility::Public,
        ]);
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->post(route('inspirational-resources.gratitude-journal.reactions.toggle', $registrationPost));

        $response->assertNotFound();
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_reactions_are_disabled_and_the_endpoint_404s_when_the_config_is_off(): void
    {
        config(['features.gratitude_journal_reactions_enabled' => false]);
        $entry = $this->publicEntry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));

        $response->assertNotFound();
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_reactions_are_available_when_the_config_is_on(): void
    {
        config(['features.gratitude_journal_reactions_enabled' => true]);
        $entry = $this->publicEntry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));

        $response->assertRedirect();
        $this->assertSame(1, Reaction::query()->count());
    }

    // --- Feed page rendering ---

    public function test_the_feed_shows_the_reaction_control_when_enabled(): void
    {
        config(['features.gratitude_journal_reactions_enabled' => true]);
        $entry = $this->publicEntry();
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertSee('data-reaction-form', false);
        $response->assertSee('🙌');
    }

    /**
     * A guest sees the 🙌 emoji as a plain link straight to registration
     * (no interactive form/fetch involved) rather than the authenticated
     * member's toggle form.
     */
    public function test_the_feed_shows_a_register_link_for_guests_when_reactions_are_enabled(): void
    {
        config(['features.gratitude_journal_reactions_enabled' => true]);
        $this->publicEntry();

        $response = $this->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertSee('🙌');
        $response->assertSee(route('register.show'), false);
        $response->assertDontSee('data-reaction-form', false);
    }

    public function test_the_feed_hides_the_reaction_control_when_disabled(): void
    {
        config(['features.gratitude_journal_reactions_enabled' => false]);
        $this->publicEntry();
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertDontSee('data-reaction-form', false);
    }

    public function test_the_feed_shows_the_current_reaction_count(): void
    {
        config(['features.gratitude_journal_reactions_enabled' => true]);
        $entry = $this->publicEntry();
        $reactor = User::factory()->create();
        $viewer = User::factory()->create();
        $this->actingAs($reactor)->post(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));

        $response = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertSee('data-reaction-count', false);
        $response->assertSeeInOrder(['🙌', '1'], false);
    }

    public function test_the_feed_marks_the_button_pressed_when_the_viewer_has_already_reacted(): void
    {
        config(['features.gratitude_journal_reactions_enabled' => true]);
        $entry = $this->publicEntry();
        $viewer = User::factory()->create();
        $this->actingAs($viewer)->post(route('inspirational-resources.gratitude-journal.reactions.toggle', $entry));

        $response = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertSee('aria-pressed="true"', false);
    }

    public function test_pagination_still_works_with_reactions_present(): void
    {
        config(['features.gratitude_journal_reactions_enabled' => true]);
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $action = new CreateGratitudeJournalEntryAction;

        $entries = collect(range(1, 11))->map(
            fn (int $i) => $action->handle($author, "Reaction pagination entry {$i}.", GratitudeJournalVisibility::Public)
        );
        $this->actingAs($viewer)->post(route('inspirational-resources.gratitude-journal.reactions.toggle', $entries->first()));

        $firstPage = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal'));
        $firstPage->assertOk();

        $secondPage = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal', ['page' => 2]));
        $secondPage->assertOk();
    }

    private function publicEntry(GratitudeJournalVisibility $visibility = GratitudeJournalVisibility::Public): LightPost
    {
        $author = User::factory()->create();

        return (new CreateGratitudeJournalEntryAction)->handle($author, 'A public feed entry for reaction testing.', $visibility);
    }
}
