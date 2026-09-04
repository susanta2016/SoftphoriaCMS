<?php

namespace Tests\Feature\GratitudeJournal;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage's "Latest Gratitude" carousel (HomeController::
 * latestGratitudeEntries()) — a public Journal entry is what this section
 * shows (client-confirmed, 2026-09-04: journal-only, registration Light
 * Posts excluded — see tests/Feature/HomeLightPostsTest.php for that
 * boundary's own regression coverage). No second homepage query/widget was
 * introduced; this is still the same single display slot.
 */
class GratitudeJournalVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_journal_entry_defaults_to_public_when_visibility_is_not_specified(): void
    {
        $user = User::factory()->create();

        $entry = (new CreateGratitudeJournalEntryAction)->handle($user, 'Grateful, unspecified visibility.');

        $this->assertTrue($entry->is_public);
    }

    /**
     * Regression guard for a real bug: GratitudeJournalController::store()
     * previously called $request->boolean('is_public', true) — an
     * unchecked HTML checkbox is never sent in the POST body at all, so
     * "is_public absent" was being read as the hardcoded `true` default
     * instead of as unchecked/false, silently saving a new entry as Public
     * regardless of what the member actually selected on the New Entry
     * form. update() never had this bug (no default there), which is why
     * editing an entry's visibility always worked correctly. This POST
     * deliberately omits `is_public` entirely, the same as a real browser
     * submits when the checkbox is unchecked.
     */
    public function test_submitting_the_new_entry_form_with_the_public_checkbox_unchecked_creates_a_private_entry(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => 'Grateful, submitted with the checkbox unchecked.',
        ]);

        $response->assertRedirect(route('account.gratitude-journal.index'));

        $entry = $user->lightPosts()->journal()->firstOrFail();
        $this->assertFalse($entry->is_public);
    }

    public function test_submitting_the_new_entry_form_with_the_public_checkbox_checked_creates_a_public_entry(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => 'Grateful, submitted with the checkbox checked.',
            'is_public' => '1',
        ]);

        $response->assertRedirect(route('account.gratitude-journal.index'));

        $entry = $user->lightPosts()->journal()->firstOrFail();
        $this->assertTrue($entry->is_public);
    }

    public function test_a_private_journal_entry_does_not_appear_on_the_homepage(): void
    {
        $user = User::factory()->create(['name' => 'Quiet Journaler']);
        (new CreateGratitudeJournalEntryAction)->handle($user, 'A private journal thought.', false);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('A private journal thought.');
    }

    public function test_a_public_journal_entry_can_appear_on_the_homepage(): void
    {
        $user = User::factory()->create(['name' => 'Open Journaler']);
        (new CreateGratitudeJournalEntryAction)->handle($user, 'A public journal thought.', true);

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
            $action->handle($user, "Journal gratitude number {$i}.", true);
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
