<?php

namespace Tests\Feature\Music;

use App\Models\Media;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Modules\Music\Models\TrackListen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The sole writer of track_listens — called by the frontend only on the
 * <audio> element's `ended` event (a genuinely completed playback). This is
 * a listening limit, entirely separate from DownloadLog/download limits.
 *
 * The response's listens_today/daily_limit/limit_reached fields (added
 * 2026-08-31) are what fixes the stale-player-state bug: app.js reacts to
 * limit_reached immediately instead of waiting for a future stream request
 * to fail — see resources/js/app.js's `ended` handler and
 * TrackStreamControllerTest for the server-side enforcement these fields
 * only ever *report*, never replace.
 */
class TrackListenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_record_a_completed_listen(): void
    {
        $track = $this->publishedTrack();

        $response = $this->post(route('music.tracks.listen-complete', $track));

        $response->assertRedirect(route('login'));
        $this->assertSame(0, TrackListen::query()->count());
    }

    public function test_an_authenticated_user_recording_a_completed_listen_increments_their_count(): void
    {
        $track = $this->publishedTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.listen-complete', $track));

        $response->assertOk();
        $this->assertSame(1, TrackListen::query()->where('user_id', $user->id)->where('track_id', $track->id)->count());
    }

    /**
     * "user with quota 4/5 completes a song": three prior listens today,
     * this completion is their fourth — still under the limit, so the
     * player must be told it can continue normally.
     */
    public function test_a_completion_that_stays_under_the_quota_reports_limit_not_reached(): void
    {
        config(['features.registered_user_whole_song_listens_per_day' => 5]);
        $user = User::factory()->create();
        $this->recordPriorListens($user, 3);
        $track = $this->publishedTrack();

        $response = $this->actingAs($user)->post(route('music.tracks.listen-complete', $track));

        $response->assertOk();
        $response->assertJson([
            'status' => 'recorded',
            'listens_today' => 4,
            'daily_limit' => 5,
            'limit_reached' => false,
        ]);
    }

    /**
     * "user with quota 5/5 completes a song": four prior listens today,
     * this completion is their fifth and reaches the configured limit —
     * the response must say so, since this is the sole signal app.js uses
     * to immediately stop the player on the current page.
     */
    public function test_the_completion_that_reaches_the_quota_reports_limit_reached(): void
    {
        config(['features.registered_user_whole_song_listens_per_day' => 5]);
        $user = User::factory()->create();
        $this->recordPriorListens($user, 4);
        $track = $this->publishedTrack();

        $response = $this->actingAs($user)->post(route('music.tracks.listen-complete', $track));

        $response->assertOk();
        $response->assertJson([
            'status' => 'recorded',
            'listens_today' => 5,
            'daily_limit' => 5,
            'limit_reached' => true,
        ]);
    }

    /**
     * The configured value, not a hard-coded 5, must be what determines
     * limit_reached — proven with a limit that is deliberately not 5.
     */
    public function test_the_configured_daily_limit_is_respected_not_assumed_to_be_five(): void
    {
        config(['features.registered_user_whole_song_listens_per_day' => 2]);
        $user = User::factory()->create();
        $this->recordPriorListens($user, 1);
        $track = $this->publishedTrack();

        $response = $this->actingAs($user)->post(route('music.tracks.listen-complete', $track));

        $response->assertOk();
        $response->assertJson([
            'listens_today' => 2,
            'daily_limit' => 2,
            'limit_reached' => true,
        ]);
    }

    /**
     * The exact regression this fix addresses: once a completion reports
     * limit_reached, the server (TrackStreamController, via the same
     * DailyListenQuota computation) must independently deny the very next
     * stream request too — proving the frontend's new immediate reaction
     * is backed by real enforcement, not merely a UI nicety a stale client
     * could ignore.
     */
    public function test_after_the_quota_reaching_completion_the_next_stream_request_is_denied_by_the_server(): void
    {
        config(['features.registered_user_whole_song_listens_per_day' => 5]);
        $user = User::factory()->create();
        $this->recordPriorListens($user, 4);
        $completedTrack = $this->publishedTrack();

        $this->actingAs($user)
            ->post(route('music.tracks.listen-complete', $completedTrack))
            ->assertJson(['limit_reached' => true]);

        $anotherTrack = $this->publishedTrackWithAudio();

        $this->actingAs($user)
            ->get(route('music.tracks.stream', $anotherTrack))
            ->assertForbidden();
    }

    private function recordPriorListens(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            TrackListen::query()->create([
                'user_id' => $user->id,
                'track_id' => $this->publishedTrack()->id,
            ]);
        }
    }

    private function publishedTrackWithAudio(): Track
    {
        Storage::fake('local');
        $media = new Media;
        $media->disk = 'local';
        $media->path = 'media/audio/quota-test-'.uniqid().'.mp3';
        Storage::disk('local')->put($media->path, 'fake-audio-bytes');
        $media->original_filename = 'quota-test.mp3';
        $media->mime_type = 'audio/mpeg';
        $media->size = 17;
        $media->visibility = 'protected';
        $media->save();

        return $this->publishedTrack(['audio_media_id' => $media->id, 'duration_seconds' => 180]);
    }

    public function test_a_completed_listen_is_scoped_to_the_recording_user_only(): void
    {
        $track = $this->publishedTrack();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)->post(route('music.tracks.listen-complete', $track));

        $this->assertSame(1, TrackListen::query()->where('user_id', $userA->id)->count());
        $this->assertSame(0, TrackListen::query()->where('user_id', $userB->id)->count());
    }

    public function test_a_draft_tracks_completion_beacon_404s(): void
    {
        $single = Single::query()->create([
            'title' => 'Draft Beacon Single',
            'slug' => 'draft-beacon-single',
            'status' => ReleaseStatus::Published,
        ]);
        $track = Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Draft Beacon Track',
            'slug' => 'draft-beacon-track',
            'status' => TrackStatus::Draft,
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.listen-complete', $track));

        $response->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedTrack(array $overrides = []): Track
    {
        $single = Single::query()->create([
            'title' => 'Listen Complete Single',
            'slug' => 'listen-complete-single-'.uniqid(),
            'status' => ReleaseStatus::Published,
        ]);

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Listen Complete Track',
            'slug' => 'listen-complete-track-'.uniqid(),
            'status' => TrackStatus::Published,
            ...$overrides,
        ]);
    }
}
