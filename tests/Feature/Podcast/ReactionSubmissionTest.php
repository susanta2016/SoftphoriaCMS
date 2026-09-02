<?php

namespace Tests\Feature\Podcast;

use App\Models\Reaction;
use App\Models\Review;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 🙌 reaction on a Podcast Episode — fully independent of
 * Tests\Feature\Podcast\ReviewSubmissionTest's comment coverage
 * (client-confirmed, 2026-09-02: a member reacts, comments, does both, or
 * does neither). App\Models\Reaction is a separate table/model from
 * App\Models\Review — never a repurposed star rating.
 */
class ReactionSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_react_and_is_redirected_to_login(): void
    {
        $episode = $this->episode();

        $response = $this->post(route('podcast.episodes.reactions.toggle', $episode));

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_an_authenticated_user_can_react(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reactions.toggle', $episode));

        $response->assertRedirect();
        $this->assertSame(1, Reaction::query()->count());
        $reaction = Reaction::query()->first();
        $this->assertSame($user->getKey(), $reaction->user_id);
        $this->assertSame($episode->getKey(), $reaction->reactable_id);
        $this->assertSame(PodcastEpisode::class, $reaction->reactable_type);
    }

    // --- Async (fetch) endpoint — client-confirmed reversal, 2026-09-02:
    // the 🙌 tap is now a JS fetch, not a full page reload. postJson()
    // sends Accept: application/json, exactly like resources/js/app.js's
    // real fetch() call, so the controller takes its $request->wantsJson()
    // branch and returns JSON instead of a redirect. ---

    public function test_the_async_endpoint_returns_an_appropriate_json_response(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('podcast.episodes.reactions.toggle', $episode));

        $response->assertOk();
        $response->assertJson(['reacted' => true, 'count' => 1]);
    }

    public function test_the_async_endpoint_reports_reacted_false_and_a_lower_count_after_toggling_off(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('podcast.episodes.reactions.toggle', $episode));
        $response = $this->actingAs($user)->postJson(route('podcast.episodes.reactions.toggle', $episode));

        $response->assertOk();
        $response->assertJson(['reacted' => false, 'count' => 0]);
    }

    /**
     * bootstrap/app.php scopes JSON exception rendering to `api/*` paths
     * only (`shouldRenderJsonWhen`), site-wide — this route isn't under
     * `/api`, so even a request with Accept: application/json still gets
     * the normal HTML redirect-to-login for an unauthenticated request,
     * same as the plain-POST guest test above. Documented here rather than
     * changing that global exception config for this one endpoint — the
     * frontend fetch's own catch-all (resources/js/app.js) already falls
     * back to a real form submit on anything that isn't a clean JSON
     * response, so the guest still lands on the login page either way.
     */
    public function test_a_guest_requesting_json_still_gets_redirected_to_login(): void
    {
        $episode = $this->episode();

        $response = $this->postJson(route('podcast.episodes.reactions.toggle', $episode));

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_an_unpublished_episode_returns_404_from_the_async_endpoint(): void
    {
        $podcast = Podcast::query()->create([
            'title' => 'Draft Podcast Async '.uniqid(),
            'slug' => 'draft-podcast-async-'.uniqid(),
            'status' => PodcastStatus::Published,
        ]);
        $episode = PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Draft Episode Async',
            'slug' => 'draft-episode-async-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('podcast.episodes.reactions.toggle', $episode));

        $response->assertStatus(404);
    }

    public function test_the_reaction_count_increments_on_the_public_page(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reactions.toggle', $episode));

        $response = $this->get(route('podcast.episodes.show', $episode));
        $response->assertOk();
        $response->assertSee('🙌');
        $response->assertSee('1');
    }

    public function test_toggling_again_removes_the_reaction_and_the_count_decreases(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reactions.toggle', $episode));
        $this->assertSame(1, Reaction::query()->count());

        $this->actingAs($user)->post(route('podcast.episodes.reactions.toggle', $episode));
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_the_same_user_cannot_create_a_duplicate_reaction(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        Reaction::query()->create([
            'reactable_type' => PodcastEpisode::class,
            'reactable_id' => $episode->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(QueryException::class);

        Reaction::query()->create([
            'reactable_type' => PodcastEpisode::class,
            'reactable_id' => $episode->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_two_different_users_can_each_react_to_the_same_episode(): void
    {
        $episode = $this->episode();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)->post(route('podcast.episodes.reactions.toggle', $episode));
        $this->actingAs($userB)->post(route('podcast.episodes.reactions.toggle', $episode));

        $this->assertSame(2, Reaction::query()->count());
    }

    public function test_a_user_can_react_without_leaving_a_comment(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reactions.toggle', $episode));

        $this->assertSame(1, Reaction::query()->count());
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_user_can_comment_without_reacting(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'A comment with no reaction.',
        ]);

        $this->assertSame(1, Review::query()->count());
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_a_user_can_do_both_independently(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'A comment alongside a reaction.',
        ]);
        $this->actingAs($user)->post(route('podcast.episodes.reactions.toggle', $episode));

        $this->assertSame(1, Review::query()->count());
        $this->assertSame(1, Reaction::query()->count());
    }

    public function test_an_unpublished_episode_cannot_receive_a_reaction(): void
    {
        $podcast = Podcast::query()->create([
            'title' => 'Draft Podcast '.uniqid(),
            'slug' => 'draft-podcast-'.uniqid(),
            'status' => PodcastStatus::Published,
        ]);
        $episode = PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Draft Episode',
            'slug' => 'draft-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reactions.toggle', $episode));

        $response->assertNotFound();
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_reacting_to_one_episode_never_affects_another_episodes_count(): void
    {
        $episodeA = $this->episode();
        $episodeB = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reactions.toggle', $episodeA));

        $this->assertSame(1, $episodeA->reactions()->count());
        $this->assertSame(0, $episodeB->reactions()->count());
    }

    private function episode(): PodcastEpisode
    {
        $podcast = Podcast::query()->create([
            'title' => 'Reaction Test Podcast '.uniqid(),
            'slug' => 'reaction-test-podcast-'.uniqid(),
            'status' => PodcastStatus::Published,
        ]);

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Reaction Test Episode',
            'slug' => 'reaction-test-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
        ]);
    }
}
