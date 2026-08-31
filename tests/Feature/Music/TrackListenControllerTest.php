<?php

namespace Tests\Feature\Music;

use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Modules\Music\Models\TrackListen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sole writer of track_listens — called by the frontend only on the
 * <audio> element's `ended` event (a genuinely completed playback). This is
 * a listening limit, entirely separate from DownloadLog/download limits.
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

    private function publishedTrack(): Track
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
        ]);
    }
}
