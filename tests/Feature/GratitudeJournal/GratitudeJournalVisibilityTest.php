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
 * Visibility is the two-state App\Enums\GratitudeJournalVisibility
 * (simplified from three states to two, 2026-09-10 — the previous
 * "Community" state was removed entirely; see GratitudeJournalFeedTest for
 * the shared feed's own coverage, now Public-scoped, and
 * GratitudeJournalAuthorizationTest for the owner-only Private state's
 * coverage).
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

    /**
     * Community no longer exists at the application level — a request that
     * still submits the legacy value falls back to Public, the same
     * behavior as an entirely missing/unrecognized value.
     */
    public function test_submitting_the_legacy_community_value_is_no_longer_valid_and_falls_back_to_public(): void
    {
        $user = User::factory()->create();

        $this->assertNull(GratitudeJournalVisibility::tryFrom('community'));

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => 'Grateful, submitted with the legacy community value.',
            'visibility' => 'community',
        ]);

        $response->assertRedirect(route('account.gratitude-journal.index'));

        $entry = $user->lightPosts()->journal()->firstOrFail();
        $this->assertSame(GratitudeJournalVisibility::Public, $entry->visibility);
    }

    public function test_the_visibility_enum_contains_exactly_public_and_private(): void
    {
        $this->assertSame(
            ['public', 'private'],
            array_map(fn (GratitudeJournalVisibility $case) => $case->value, GratitudeJournalVisibility::cases()),
        );
    }

    public function test_a_private_journal_entry_does_not_appear_on_the_homepage(): void
    {
        $user = User::factory()->create(['name' => 'Quiet Journaler']);
        (new CreateGratitudeJournalEntryAction)->handle($user, 'A private journal thought.', GratitudeJournalVisibility::Private);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('A private journal thought.');
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
