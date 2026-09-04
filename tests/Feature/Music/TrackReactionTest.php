<?php

namespace Tests\Feature\Music;

use App\Models\Reaction;
use App\Models\Review;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 🙌 reaction on a Music Track — fully independent of
 * Tests\Feature\Music\TrackReviewTest's comment coverage (client-confirmed,
 * 2026-09-02: a member reacts, comments, does both, or does neither).
 * App\Models\Reaction is a separate table/model from App\Models\Review —
 * never a repurposed star rating.
 */
class TrackReactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_react_and_is_redirected_to_login(): void
    {
        $track = $this->publishedSingleTrack();

        $response = $this->post(route('music.tracks.reactions.toggle', $track));

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_an_authenticated_user_can_react(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reactions.toggle', $track));

        $response->assertRedirect();
        $this->assertSame(1, Reaction::query()->count());
        $reaction = Reaction::query()->first();
        $this->assertSame($user->getKey(), $reaction->user_id);
        $this->assertSame($track->getKey(), $reaction->reactable_id);
        $this->assertSame(Track::class, $reaction->reactable_type);
    }

    // --- Async (fetch) endpoint — client-confirmed reversal, 2026-09-02:
    // the 🙌 tap is now a JS fetch, not a full page reload. postJson()
    // sends Accept: application/json, exactly like resources/js/app.js's
    // real fetch() call, so the controller takes its $request->wantsJson()
    // branch and returns JSON instead of a redirect. ---

    public function test_the_async_endpoint_returns_an_appropriate_json_response(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('music.tracks.reactions.toggle', $track));

        $response->assertOk();
        $response->assertJson(['reacted' => true, 'count' => 1]);
    }

    public function test_the_async_endpoint_reports_reacted_false_and_a_lower_count_after_toggling_off(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('music.tracks.reactions.toggle', $track));
        $response = $this->actingAs($user)->postJson(route('music.tracks.reactions.toggle', $track));

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
        $track = $this->publishedSingleTrack();

        $response = $this->postJson(route('music.tracks.reactions.toggle', $track));

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_an_unpublished_track_returns_404_from_the_async_endpoint(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Draft]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Draft]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('music.tracks.reactions.toggle', $track));

        $response->assertStatus(404);
    }

    public function test_the_reaction_count_increments_on_the_public_page(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reactions.toggle', $track));

        $response = $this->get(route('music.singles.show', $single));
        $response->assertOk();
        $response->assertSee('🙌');
        $response->assertSee('1');
    }

    public function test_toggling_again_removes_the_reaction_and_the_count_decreases(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reactions.toggle', $track));
        $this->assertSame(1, Reaction::query()->count());

        $this->actingAs($user)->post(route('music.tracks.reactions.toggle', $track));
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_the_same_user_cannot_create_a_duplicate_reaction(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        // Two rapid taps that both land as "react" (not toggle-off) can only
        // happen if the app-level check races — the toggle logic itself
        // means a second real request always removes the first reaction.
        // The database's own unique index is the backstop tested directly
        // here, independent of the toggle endpoint's own correct behavior.
        Reaction::query()->create([
            'reactable_type' => Track::class,
            'reactable_id' => $track->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(QueryException::class);

        Reaction::query()->create([
            'reactable_type' => Track::class,
            'reactable_id' => $track->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_two_different_users_can_each_react_to_the_same_track(): void
    {
        $track = $this->publishedSingleTrack();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)->post(route('music.tracks.reactions.toggle', $track));
        $this->actingAs($userB)->post(route('music.tracks.reactions.toggle', $track));

        $this->assertSame(2, Reaction::query()->count());
    }

    public function test_a_user_can_react_without_leaving_a_comment(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reactions.toggle', $track));

        $this->assertSame(1, Reaction::query()->count());
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_user_can_comment_without_reacting(): void
    {
        // Comments are off by default for Music (config('features.music_comments_enabled'))
        // — enabled here only to exercise this cross-feature independence
        // assertion, not because the shipped default has changed.
        config(['features.music_comments_enabled' => true]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'A comment with no reaction.',
        ]);

        $this->assertSame(1, Review::query()->count());
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_a_user_can_do_both_independently(): void
    {
        config(['features.music_comments_enabled' => true]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'A comment alongside a reaction.',
        ]);
        $this->actingAs($user)->post(route('music.tracks.reactions.toggle', $track));

        $this->assertSame(1, Review::query()->count());
        $this->assertSame(1, Reaction::query()->count());
    }

    public function test_an_unpublished_track_cannot_receive_a_reaction(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Draft]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Draft]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reactions.toggle', $track));

        $response->assertNotFound();
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_reacting_to_one_track_never_affects_another_tracks_count(): void
    {
        $singleA = $this->single(['title' => 'Track A Single', 'slug' => 'reaction-track-a-single', 'status' => ReleaseStatus::Published]);
        $trackA = $this->track(null, $singleA, ['title' => 'Track A', 'slug' => 'reaction-track-a', 'status' => TrackStatus::Published]);
        $singleB = $this->single(['title' => 'Track B Single', 'slug' => 'reaction-track-b-single', 'status' => ReleaseStatus::Published]);
        $trackB = $this->track(null, $singleB, ['title' => 'Track B', 'slug' => 'reaction-track-b', 'status' => TrackStatus::Published]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reactions.toggle', $trackA));

        $this->assertSame(1, $trackA->reactions()->count());
        $this->assertSame(0, $trackB->reactions()->count());
    }

    /**
     * Client-confirmed (2026-09-04): the 🙌 reaction stays enabled for Music
     * by default — this test only confirms the module's own flag genuinely
     * gates the endpoint server-side (config('features.music_reactions_enabled')),
     * not that the shipped default has changed.
     */
    public function test_reactions_are_disabled_when_the_module_config_is_off(): void
    {
        config(['features.music_reactions_enabled' => false]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reactions.toggle', $track));

        $response->assertNotFound();
        $this->assertSame(0, Reaction::query()->count());
    }

    private function publishedSingleTrack(array $overrides = []): Track
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);

        return $this->track(null, $single, ['status' => TrackStatus::Published, ...$overrides]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function single(array $overrides = []): Single
    {
        return Single::query()->create([
            'title' => 'A Single',
            'slug' => 'a-single-'.uniqid(),
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function track(?Album $album, ?Single $single, array $overrides = []): Track
    {
        return Track::query()->create([
            'album_id' => $album?->id,
            'single_id' => $single?->id,
            'title' => 'A Track',
            'slug' => 'a-track-'.uniqid(),
            'status' => TrackStatus::Draft,
            ...$overrides,
        ]);
    }
}
