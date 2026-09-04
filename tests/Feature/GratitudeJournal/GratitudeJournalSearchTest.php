<?php

namespace Tests\Feature\GratitudeJournal;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gratitude Journal audit §6: LightPost::newScoutQuery()/shouldBeSearchable()
 * now additionally require source = registration, so Journal entries never
 * become individually searchable documents even when public, while a
 * registration Light Post's existing searchability is unchanged. Mirrors
 * tests/Feature/Search/SearchVisibilityTest.php's own
 * test_a_public_light_post_appears_in_search_results()/
 * test_a_private_light_post_does_not_appear_in_search_results() pattern —
 * that file is left untouched.
 */
class GratitudeJournalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_registration_light_post_still_appears_in_search_results(): void
    {
        $user = User::factory()->create();
        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'source' => LightPostSource::Registration,
            'content' => 'A wholly distinctive registration phrase '.uniqid(),
            'is_public' => true,
        ]);

        $response = $this->get(route('search.index', ['q' => 'wholly distinctive registration']));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => $results->getCollection()
            ->contains(fn ($result) => $result->url === route('light-posts.show', $post)));
    }

    public function test_a_public_journal_entry_does_not_appear_in_search_results(): void
    {
        $user = User::factory()->create();
        $phrase = 'A wholly distinctive journal phrase '.uniqid();
        (new CreateGratitudeJournalEntryAction)->handle($user, $phrase, true);

        $response = $this->get(route('search.index', ['q' => 'wholly distinctive journal']));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => $results->getCollection()->isEmpty());
    }
}
