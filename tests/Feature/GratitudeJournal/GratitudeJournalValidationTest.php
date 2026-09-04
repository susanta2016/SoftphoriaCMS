<?php

namespace Tests\Feature\GratitudeJournal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Gratitude Journal's character limit is controlled ONLY through
 * GRATITUDE_JOURNAL_MAX_LENGTH / config('features.gratitude_journal_max_length')
 * (Gratitude Journal audit §2) — deliberately independent of
 * config('features.light_post_max_length'), which the registration flow
 * still owns exclusively (see GratitudeJournalDatabaseTest and the existing
 * Registration test suite for that limit's own coverage, untouched here).
 */
class GratitudeJournalValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_maximum_length_is_100(): void
    {
        $this->assertSame(100, config('features.gratitude_journal_max_length'));
    }

    public function test_an_entry_within_the_default_limit_is_accepted(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => str_repeat('a', 100),
        ]);

        $response->assertRedirect(route('account.gratitude-journal.index'));
        $response->assertSessionDoesntHaveErrors('content');
        $this->assertSame(1, $user->lightPosts()->count());
    }

    public function test_an_entry_over_the_default_limit_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => str_repeat('a', 101),
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, $user->lightPosts()->count());
    }

    /**
     * Mirrors FreeRegistrationTest::test_a_light_message_longer_than_the_configured_limit_is_rejected()'s
     * own config(['features.light_post_max_length' => ...]) pattern, applied
     * to the Journal's own, separate config key.
     */
    public function test_a_configured_env_value_changes_the_allowed_maximum(): void
    {
        config(['features.gratitude_journal_max_length' => 20]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => str_repeat('a', 21),
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, $user->lightPosts()->count());

        $response = $this->actingAs($user)->post(route('account.gratitude-journal.store'), [
            'content' => str_repeat('a', 20),
        ]);

        $response->assertSessionDoesntHaveErrors('content');
        $this->assertSame(1, $user->lightPosts()->count());
    }

    public function test_the_existing_light_post_max_length_is_unaffected_by_the_journal_configuration(): void
    {
        config(['features.gratitude_journal_max_length' => 20]);

        $this->assertSame(500, config('features.light_post_max_length'));
    }

    public function test_the_journal_page_renders_the_configured_maximum_as_the_textarea_maxlength(): void
    {
        config(['features.gratitude_journal_max_length' => 42]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.gratitude-journal.index'));

        $response->assertOk();
        $response->assertSee('maxlength="42"', false);
        $response->assertSee('0 / 42', false);
    }
}
