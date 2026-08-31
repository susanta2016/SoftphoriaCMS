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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * The public native-playback endpoint — the only audio source the frontend
 * player uses now (see MusicControllerTest for proof external
 * MusicStreamingLink URLs are no longer rendered as the <audio> source at
 * all). Covers the two server-authoritative limits: a guest's hard
 * byte-truncated preview, and a registered user's daily completed-listen
 * quota. Purchase-based download authorization is untouched and covered
 * separately by DownloadAuthorizationTest/TrackDownloadControllerTest.
 */
class TrackStreamControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_track_with_no_audio_file_404s(): void
    {
        $track = $this->publishedTrack();

        $response = $this->get(route('music.tracks.stream', $track));

        $response->assertNotFound();
    }

    public function test_a_draft_tracks_stream_404s(): void
    {
        $media = $this->audioMedia(30, 'ID3 full track content beyond thirty seconds worth of audio bytes.');
        $track = $this->publishedTrack(['status' => TrackStatus::Draft, 'audio_media_id' => $media->id]);

        $response = $this->get(route('music.tracks.stream', $track));

        $response->assertNotFound();
    }

    /**
     * The 2026-08-31 bug: duration_seconds unknown must never fall back to
     * serving the full, untruncated file — it must fail closed (404), since
     * there is no safe way to compute a byte cap without it. Regression
     * guard independent of SavesTrackRelations' auto-fill-on-save fix (this
     * track is created directly, bypassing that action entirely, exactly
     * like a pre-existing row saved before the fix shipped).
     */
    public function test_a_guest_is_denied_rather_than_given_the_full_file_when_duration_is_unknown(): void
    {
        $fullContent = str_repeat('UNTRUNCATED-FULL-FILE-BYTES-', 500);
        $media = $this->audioMedia(0, $fullContent);
        $track = $this->publishedTrack(['audio_media_id' => $media->id, 'duration_seconds' => null]);

        $response = $this->get(route('music.tracks.stream', $track));

        $response->assertNotFound();
    }

    public function test_a_guest_receives_a_truncated_response_shorter_than_the_full_file(): void
    {
        config(['features.guest_user_listening_limit_seconds' => 30]);
        $fullContent = str_repeat('AUDIO-BYTES-', 200); // 2400 bytes
        $media = $this->audioMedia(120, $fullContent); // 120s track, 30s allowed => 1/4 of the bytes
        $track = $this->publishedTrack(['audio_media_id' => $media->id, 'duration_seconds' => 120]);

        $response = $this->get(route('music.tracks.stream', $track));

        $response->assertOk();
        $this->assertLessThan(strlen($fullContent), strlen($response->getContent()));
        $this->assertSame((int) floor(strlen($fullContent) * 30 / 120), strlen($response->getContent()));
        $this->assertSame(substr($fullContent, 0, strlen($response->getContent())), $response->getContent());
    }

    public function test_a_guest_can_never_retrieve_more_than_the_allowed_bytes_even_via_a_range_header(): void
    {
        config(['features.guest_user_listening_limit_seconds' => 30]);
        $fullContent = str_repeat('X', 1000);
        $media = $this->audioMedia(100, $fullContent);
        $track = $this->publishedTrack(['audio_media_id' => $media->id, 'duration_seconds' => 100]);

        $response = $this->withHeaders(['Range' => 'bytes=0-999'])->get(route('music.tracks.stream', $track));

        $response->assertOk();
        $this->assertSame(300, strlen($response->getContent()));
    }

    public function test_a_guest_gets_the_whole_file_when_the_track_is_already_shorter_than_the_limit(): void
    {
        config(['features.guest_user_listening_limit_seconds' => 30]);
        $fullContent = 'short-clip-bytes';
        $media = $this->audioMedia(10, $fullContent);
        $track = $this->publishedTrack(['audio_media_id' => $media->id, 'duration_seconds' => 10]);

        $response = $this->get(route('music.tracks.stream', $track));

        $response->assertOk();
        $this->assertSame($fullContent, $response->getContent());
    }

    public function test_a_registered_user_under_quota_receives_the_full_file(): void
    {
        $fullContent = str_repeat('FULL-TRACK-BYTES-', 50);
        $media = $this->audioMedia(180, $fullContent);
        $track = $this->publishedTrack(['audio_media_id' => $media->id, 'duration_seconds' => 180]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('music.tracks.stream', $track));

        $response->assertOk();
        $response->assertHeader('content-type', 'audio/mpeg');
        // A registered user's file is served via BinaryFileResponse (Range-
        // capable, streamed directly rather than buffered), so
        // getContent() is not the raw body in tests — assert the response
        // actually targets the full, untruncated file on disk instead.
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(Storage::disk($media->disk)->path($media->path), $response->baseResponse->getFile()->getPathname());
        $this->assertSame(strlen($fullContent), $response->baseResponse->getFile()->getSize());
    }

    public function test_a_registered_user_at_the_daily_quota_is_denied(): void
    {
        config(['features.registered_user_whole_song_listens_per_day' => 5]);
        $media = $this->audioMedia(180, 'bytes');
        $track = $this->publishedTrack(['audio_media_id' => $media->id, 'duration_seconds' => 180]);
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            TrackListen::query()->create(['user_id' => $user->id, 'track_id' => $track->id]);
        }

        $response = $this->actingAs($user)->get(route('music.tracks.stream', $track));

        $response->assertForbidden();
    }

    public function test_a_registered_users_quota_only_counts_todays_listens(): void
    {
        config(['features.registered_user_whole_song_listens_per_day' => 5]);
        $media = $this->audioMedia(180, 'full-bytes');
        $track = $this->publishedTrack(['audio_media_id' => $media->id, 'duration_seconds' => 180]);
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $listen = TrackListen::query()->create(['user_id' => $user->id, 'track_id' => $track->id]);
            $listen->forceFill(['created_at' => now()->subDay()])->save();
        }

        $response = $this->actingAs($user)->get(route('music.tracks.stream', $track));

        $response->assertOk();
    }

    private function publishedTrack(array $overrides = []): Track
    {
        $single = Single::query()->create([
            'title' => 'Stream Test Single',
            'slug' => 'stream-test-single-'.uniqid(),
            'status' => ReleaseStatus::Published,
        ]);

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Stream Test Track',
            'slug' => 'stream-test-track-'.uniqid(),
            'status' => TrackStatus::Published,
            ...$overrides,
        ]);
    }

    private function audioMedia(int $durationSeconds, string $content): Media
    {
        Storage::fake('local');
        $path = 'media/audio/stream-test-'.uniqid().'.mp3';
        Storage::disk('local')->put($path, $content);

        return Media::query()->create([
            'disk' => 'local',
            'path' => $path,
            'original_filename' => 'stream-test.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => strlen($content),
            'visibility' => 'protected',
        ]);
    }
}
