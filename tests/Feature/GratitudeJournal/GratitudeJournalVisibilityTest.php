<?php

namespace Tests\Feature\GratitudeJournal;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Enums\GratitudeJournalVisibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage's "Latest Gratitude" carousel (HomeController::
 * latestGratitudeEntries()) — a Public Journal entry is what this section
 * shows (client-confirmed, 2026-09-04: journal-only, registration Light
 * Posts excluded — see tests/Feature/HomeLightPostsTest.php for that
 * boundary's own regression coverage). No second homepage query/widget was
 * introduced; this is still the same single display slot.
 *
 * Visibility is now the three-state App\Enums\GratitudeJournalVisibility
 * (Gratitude Journal three-state visibility change, 2026-09-05), replacing
 * the previous is_public boolean/checkbox. The old "Private" state — which
 * was actually always the shared-feed-visible state, never truly private —
 * is now App\Enums\GratitudeJournalVisibility::Community; see
 * GratitudeJournalFeedTest for that page's own coverage and
 * GratitudeJournalAuthorizationTest for the new, genuinely owner-only
 * Private state's coverage.
 */
class GratitudeJournalVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_journal_entry_defaults_to_public_when_visibility_is_not_specified(): void
    {
        $user = User::factory()->create();

        $entry = (new CreateGratitudeJournalEntryAction)->handle($user, 'Grateful, unspecified visibility.');

        $this->assertSame(GratitudeJournalVisibility::Public, $entry->visibility);
    }

    /**
     * The account form's visibility control is now a closed 3-option
     * selector (radio group), not a checkbox — there is no longer a
     * meaningful "unchecked" state to submit. A request that omits the
     * `visibility` field entirely (e.g. a stale/malformed submission) falls
     * back to Public, the same default the Action itself uses.
     */
    public function test_submitting_the_new_entry_form_without_a_visibility_field_creates_a_public_entry(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => 'Grateful, submitted with no visibility field.',
        ]);

        $response->assertRedirect(route('account.gratitude-journal.index'));

        $entry = $user->lightPosts()->journal()->firstOrFail();
        $this->assertSame(GratitudeJournalVisibility::Public, $entry->visibility);
    }

    public function test_submitting_the_new_entry_form_with_public_selected_creates_a_public_entry(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => 'Grateful, submitted with Public selected.',
            'visibility' => 'public',
        ]);

        $response->assertRedirect(route('account.gratitude-journal.index'));

        $entry = $user->lightPosts()->journal()->firstOrFail();
        $this->assertSame(GratitudeJournalVisibility::Public, $entry->visibility);
    }

    public function test_submitting_the_new_entry_form_with_private_selected_creates_a_private_entry(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => 'Grateful, submitted with Private selected.',
            'visibility' => 'private',
        ]);

        $response->assertRedirect(route('account.gratitude-journal.index'));

        $entry = $user->lightPosts()->journal()->firstOrFail();
        $this->assertSame(GratitudeJournalVisibility::Private, $entry->visibility);
    }

    public function test_submitting_the_new_entry_form_with_for_community_selected_creates_a_community_entry(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => 'Grateful, submitted with For Community selected.',
            'visibility' => 'community',
        ]);

        $response->assertRedirect(route('account.gratitude-journal.index'));

        $entry = $user->lightPosts()->journal()->firstOrFail();
        $this->assertSame(GratitudeJournalVisibility::Community, $entry->visibility);
    }

    public function test_a_private_journal_entry_does_not_appear_on_the_homepage(): void
    {
        $user = User::factory()->create(['name' => 'Quiet Journaler']);
        (new CreateGratitudeJournalEntryAction)->handle($user, 'A private journal thought.', GratitudeJournalVisibility::Private);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('A private journal thought.');
    }

    public function test_a_community_journal_entry_does_not_appear_on_the_homepage(): void
    {
        $user = User::factory()->create(['name' => 'Community Journaler']);
        (new CreateGratitudeJournalEntryAction)->handle($user, 'A community journal thought.', GratitudeJournalVisibility::Community);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('A community journal thought.');
    }

    public function test_a_public_journal_entry_can_appear_on_the_homepage(): void
    {
        $user = User::factory()->create(['name' => 'Open Journaler']);
        (new CreateGratitudeJournalEntryAction)->handle($user, 'A public journal thought.', GratitudeJournalVisibility::Public);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Open Journaler');
        $response->assertSee('A public journal thought.');
    }

    public function test_a_public_journal_entry_shares_the_homepages_existing_eight_entry_limit(): void
    {
        $user = User::factory()->create();
        $action = new CreateGratitudeJournalEntryAction;

        foreach (range(1, 9) as $i) {
            $action->handle($user, "Journal gratitude number {$i}.", GratitudeJournalVisibility::Public);
        }

        $response = $this->get(route('home'));

        $response->assertOk();
        $shown = 0;
        foreach (range(1, 9) as $i) {
            if (str_contains($response->getContent(), "Journal gratitude number {$i}.")) {
                $shown++;
            }
        }
        $this->assertSame(8, $shown);
    }
}
