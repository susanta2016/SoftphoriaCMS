<?php

namespace Tests\Feature\GratitudeJournal;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Enums\GratitudeJournalVisibility;
use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every Gratitude Journal mutation route is scoped through the authenticated
 * member's own entries — GratitudeJournalController::authorizeOwnJournalEntry()
 * 404s (never 403, to avoid signaling which ids exist) whenever the bound
 * entry either belongs to someone else or isn't a journal entry at all
 * (source = registration) — see the Gratitude Journal audit §8.
 */
class GratitudeJournalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('account.gratitude-journal.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_member_sees_only_their_own_entries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $action = new CreateGratitudeJournalEntryAction;
        $action->handle($owner, 'My own gratitude entry.');
        $action->handle($other, 'Someone elses gratitude entry.');

        $response = $this->actingAs($owner)->get(route('account.gratitude-journal.index'));

        $response->assertOk();
        $response->assertSee('My own gratitude entry.');
        $response->assertDontSee('Someone elses gratitude entry.');
    }

    public function test_a_members_own_private_entry_is_visible_to_them_on_their_journal_page(): void
    {
        $owner = User::factory()->create();
        (new CreateGratitudeJournalEntryAction)->handle($owner, 'A private reflection.', GratitudeJournalVisibility::Private);

        $response = $this->actingAs($owner)->get(route('account.gratitude-journal.index'));

        $response->assertOk();
        $response->assertSee('A private reflection.');
    }

    public function test_a_member_cannot_update_another_members_entry(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $entry = (new CreateGratitudeJournalEntryAction)->handle($owner, 'Original content.');

        $response = $this->actingAs($attacker)->put(route('account.gratitude-journal.update', $entry), [
            'content' => 'Hijacked content.',
        ]);

        $response->assertNotFound();
        $this->assertSame('Original content.', $entry->fresh()->content);
    }

    public function test_a_member_cannot_delete_another_members_entry(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $entry = (new CreateGratitudeJournalEntryAction)->handle($owner, 'Should survive.');

        $response = $this->actingAs($attacker)->delete(route('account.gratitude-journal.destroy', $entry));

        $response->assertNotFound();
        $this->assertNotNull($entry->fresh());
    }

    public function test_a_member_can_change_their_own_entrys_visibility(): void
    {
        $owner = User::factory()->create();
        $entry = (new CreateGratitudeJournalEntryAction)->handle($owner, 'Flexible visibility.', GratitudeJournalVisibility::Public);

        $response = $this->actingAs($owner)->put(route('account.gratitude-journal.update', $entry), [
            'content' => 'Flexible visibility.',
            'visibility' => 'private',
        ]);

        $response->assertRedirect(route('account.gratitude-journal.index'));
        $this->assertSame(GratitudeJournalVisibility::Private, $entry->fresh()->visibility);
    }

    /**
     * Covers the third state specifically — changing an entry to For
     * Community, distinct from the Public/Private pair above.
     */
    public function test_a_member_can_change_their_own_entrys_visibility_to_for_community(): void
    {
        $owner = User::factory()->create();
        $entry = (new CreateGratitudeJournalEntryAction)->handle($owner, 'Flexible visibility.', GratitudeJournalVisibility::Public);

        $response = $this->actingAs($owner)->put(route('account.gratitude-journal.update', $entry), [
            'content' => 'Flexible visibility.',
            'visibility' => 'community',
        ]);

        $response->assertRedirect(route('account.gratitude-journal.index'));
        $this->assertSame(GratitudeJournalVisibility::Community, $entry->fresh()->visibility);
    }

    public function test_a_member_can_edit_and_delete_their_own_entry(): void
    {
        $owner = User::factory()->create();
        $entry = (new CreateGratitudeJournalEntryAction)->handle($owner, 'Old content.');

        $this->actingAs($owner)->put(route('account.gratitude-journal.update', $entry), [
            'content' => 'New content.',
            'visibility' => 'public',
        ])->assertRedirect(route('account.gratitude-journal.index'));

        $this->assertSame('New content.', $entry->fresh()->content);

        $this->actingAs($owner)->delete(route('account.gratitude-journal.destroy', $entry))
            ->assertRedirect(route('account.gratitude-journal.index'));

        $this->assertNull(LightPost::query()->find($entry->id));
    }

    /**
     * A member's own registration-time Light Post must never be reachable
     * through the Journal's edit/delete routes — those are the one route
     * shape in this codebase that takes a route-bound id belonging to the
     * signed-in member, and it must stay scoped to source = journal only.
     */
    public function test_a_members_own_registration_light_post_cannot_be_edited_through_journal_routes(): void
    {
        $user = User::factory()->create();
        $registrationPost = LightPost::query()->create([
            'user_id' => $user->id,
            'source' => LightPostSource::Registration,
            'content' => 'My registration post.',
            'visibility' => GratitudeJournalVisibility::Public,
        ]);

        $response = $this->actingAs($user)->put(route('account.gratitude-journal.update', $registrationPost), [
            'content' => 'Hijacked via journal route.',
        ]);

        $response->assertNotFound();
        $this->assertSame('My registration post.', $registrationPost->fresh()->content);
    }

    public function test_a_members_own_registration_light_post_cannot_be_deleted_through_journal_routes(): void
    {
        $user = User::factory()->create();
        $registrationPost = LightPost::query()->create([
            'user_id' => $user->id,
            'source' => LightPostSource::Registration,
            'content' => 'My registration post.',
            'visibility' => GratitudeJournalVisibility::Public,
        ]);

        $response = $this->actingAs($user)->delete(route('account.gratitude-journal.destroy', $registrationPost));

        $response->assertNotFound();
        $this->assertNotNull($registrationPost->fresh());
    }
}
