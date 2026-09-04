<?php

namespace Tests\Feature\GratitudeJournal;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage keeps using its existing HomeController::latestLightPosts()
 * mechanism unchanged (LightPost::query()->public()->...) — Gratitude
 * Journal audit §4: a public Journal entry is just another public
 * light_posts row, so it surfaces there for free, with no second homepage
 * query/widget. Mirrors tests/Feature/HomeLightPostsTest.php's own
 * assertions, which remain untouched and must keep passing unmodified.
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

    public function test_a_public_journal_entry_shares_the_homepages_existing_four_entry_limit_with_registration_posts(): void
    {
        $user = User::factory()->create();
        $action = new CreateGratitudeJournalEntryAction;

        foreach (range(1, 5) as $i) {
            $action->handle($user, "Journal gratitude number {$i}.", true);
        }

        $response = $this->get(route('home'));

        $response->assertOk();
        $shown = 0;
        foreach (range(1, 5) as $i) {
            if (str_contains($response->getContent(), "Journal gratitude number {$i}.")) {
                $shown++;
            }
        }
        $this->assertSame(4, $shown);
    }
}
