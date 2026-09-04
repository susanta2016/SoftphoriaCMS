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
 * The Gratitude Journal shared member feed at
 * /inspirational-resources/gratitude-journal (GratitudeJournalFeedController)
 * — a READ-ONLY page, deliberately separate from the member's own
 * management area at /account/gratitude-journal
 * (App\Http\Controllers\Account\GratitudeJournalController).
 *
 * "For Community" entries only (Gratitude Journal three-state visibility
 * change, 2026-09-05) — the exact same rows the old is_public = false
 * ("Private") state already showed here; that state was renamed/reused as
 * Community, not reinterpreted, so this page's actual content is unchanged.
 * A genuinely Private entry (the new state) never appears here — only in
 * its owner's own Account "Your Entries" (see
 * GratitudeJournalAuthorizationTest). Every authenticated member sees every
 * OTHER member's (and their own) Community entry here, but can never
 * create, edit, or delete anything from this page.
 */
class GratitudeJournalFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('inspirational-resources.gratitude-journal'));

        $response->assertRedirect(route('login'));
    }

    /**
     * The core visibility requirement: For Community does NOT mean
     * owner-only — every authenticated member sees every Community entry
     * here, from any author.
     */
    public function test_an_authenticated_member_sees_another_members_community_entry(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        (new CreateGratitudeJournalEntryAction)->handle($author, 'A community feed entry.', GratitudeJournalVisibility::Community);

        $response = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertSee('A community feed entry.');
    }

    /**
     * A Public journal entry's own exposure is the homepage feed instead
     * (see GratitudeJournalVisibilityTest) — it must not also appear here.
     */
    public function test_a_public_journal_entry_does_not_appear_on_this_feed(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        (new CreateGratitudeJournalEntryAction)->handle($author, 'A public feed entry.', GratitudeJournalVisibility::Public);

        $response = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertDontSee('A public feed entry.');
    }

    /**
     * A genuinely Private entry's only surface is the owner's own Account
     * "Your Entries" — it must never reach the shared feed, even for its
     * own author.
     */
    public function test_a_private_journal_entry_does_not_appear_on_this_feed_even_for_its_own_author(): void
    {
        $author = User::factory()->create();
        (new CreateGratitudeJournalEntryAction)->handle($author, 'A truly private entry.', GratitudeJournalVisibility::Private);

        $response = $this->actingAs($author)->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertDontSee('A truly private entry.');
    }

    public function test_a_registration_light_post_does_not_appear_on_this_feed(): void
    {
        $user = User::factory()->create();
        LightPost::query()->create([
            'user_id' => $user->id,
            'source' => LightPostSource::Registration,
            'content' => 'A registration-time light post.',
            'visibility' => GratitudeJournalVisibility::Public,
        ]);
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertDontSee('A registration-time light post.');
    }

    public function test_the_feed_is_paginated_at_ten_entries_per_page(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $action = new CreateGratitudeJournalEntryAction;

        foreach (range(1, 11) as $i) {
            $action->handle($author, "Feed gratitude entry number {$i}.", GratitudeJournalVisibility::Community);
        }

        $firstPage = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal'));
        $firstPage->assertOk();

        $shown = 0;
        foreach (range(1, 11) as $i) {
            if (str_contains($firstPage->getContent(), "Feed gratitude entry number {$i}.")) {
                $shown++;
            }
        }
        $this->assertSame(10, $shown);

        $secondPage = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal', ['page' => 2]));
        $secondPage->assertOk();
        $secondPage->assertSee('Feed gratitude entry number 1.');
    }

    /**
     * No create/edit/delete controls anywhere on this page, even for the
     * viewer's own entry — the page has no form posting to any of the
     * account management routes, and none of the management button labels
     * appear.
     */
    public function test_the_feed_has_no_create_edit_or_delete_controls(): void
    {
        $viewer = User::factory()->create();
        $ownEntry = (new CreateGratitudeJournalEntryAction)->handle($viewer, 'My own entry shown read-only.', GratitudeJournalVisibility::Community);

        $response = $this->actingAs($viewer)->get(route('inspirational-resources.gratitude-journal'));

        $response->assertOk();
        $response->assertSee('My own entry shown read-only.');

        // No form ever posts to the account management routes from this page.
        $response->assertDontSee(route('account.gratitude-journal.store'), false);
        $response->assertDontSee(route('account.gratitude-journal.update', $ownEntry), false);
        $response->assertDontSee(route('account.gratitude-journal.destroy', $ownEntry), false);
        $response->assertDontSee(route('account.gratitude-journal.reminder'), false);

        // No management button/label text anywhere on the page.
        $response->assertDontSee('Save Entry', false);
        $response->assertDontSee('Save Changes', false);
        $response->assertDontSee('>Edit<', false);
        $response->assertDontSee('>Delete<', false);
        $response->assertDontSee('New Entry', false);
    }
}
