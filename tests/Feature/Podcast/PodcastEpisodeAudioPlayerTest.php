<?php

namespace Tests\Feature\Podcast;

use App\Models\Media;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * The play-only <audio> player added to the episode page in place of the
 * removed "Download Audio" link (client-confirmed, 2026-09-05) — streams via
 * the new public PodcastEpisodeStreamController, open to guests and members
 * alike, same as the existing YouTube embed. See
 * PodcastEpisodeDownloadControllerTest for confirmation the backend download
 * route/controller are otherwise untouched.
 */
class PodcastEpisodeAudioPlayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_player_renders_for_a_guest_when_audio_exists(): void
    {
        $episode = $this->episodeWithAudio('audio-bytes');

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee('data-podcast-audio-player', false);
        $response->assertSee(route('podcast.episodes.stream', $episode), false);
    }

    public function test_the_player_renders_for_a_registered_user_when_audio_exists(): void
    {
        $episode = $this->episodeWithAudio('audio-bytes');
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee('data-podcast-audio-player', false);
        $response->assertSee(route('podcast.episodes.stream', $episode), false);
    }

    public function test_no_player_is_rendered_when_the_episode_has_no_audio(): void
    {
        $podcast = $this->createPodcast();
        $episode = PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'No Audio Episode',
            'slug' => 'no-audio-player-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
        ]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertDontSee('data-podcast-audio-player', false);
    }

    /**
     * The player must never expose a download affordance from our own UI —
     * controlsList="nodownload" is the browser-level hint; this only checks
     * our own markup adds no separate download link/button next to it.
     */
    public function test_the_player_has_no_download_control_in_our_markup(): void
    {
        $episode = $this->episodeWithAudio('audio-bytes');
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get(route('podcast.episodes.show', $episode));

        $response->assertSee('controlslist="nodownload"', false);
        $response->assertDontSee('Download Audio');
        $response->assertDontSee(route('podcast.episodes.download', $episode), false);
    }

    public function test_the_youtube_embed_still_renders_alongside_the_player(): void
    {
        $podcast = $this->createPodcast();
        $episode = PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Video Episode',
            'slug' => 'video-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
            'embed_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee('youtube.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_a_guest_streaming_the_audio_receives_the_full_file_inline(): void
    {
        $content = str_repeat('FULL-AUDIO-BYTES-', 50);
        $episode = $this->episodeWithAudio($content);

        $response = $this->get(route('podcast.episodes.stream', $episode));

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        // Served via BinaryFileResponse (never truncated/quota-checked,
        // unlike Music's guest preview) — assert it targets the full file on
        // disk, the same convention TrackStreamControllerTest uses for its
        // own registered-user (non-truncated) branch.
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(strlen($content), $response->baseResponse->getFile()->getSize());
    }

    public function test_a_registered_user_streaming_the_audio_receives_the_full_file(): void
    {
        $content = str_repeat('FULL-AUDIO-BYTES-', 50);
        $episode = $this->episodeWithAudio($content);
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get(route('podcast.episodes.stream', $episode));

        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(strlen($content), $response->baseResponse->getFile()->getSize());
    }

    public function test_streaming_404s_when_the_episode_has_no_audio(): void
    {
        $podcast = $this->createPodcast();
        $episode = PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'No Audio Stream',
            'slug' => 'no-audio-stream-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
        ]);

        $response = $this->get(route('podcast.episodes.stream', $episode));

        $response->assertNotFound();
    }

    public function test_streaming_404s_for_an_unpublished_episode(): void
    {
        $episode = $this->episodeWithAudio('audio-bytes', ['status' => PodcastEpisodeStatus::Draft]);

        $response = $this->get(route('podcast.episodes.stream', $episode));

        $response->assertNotFound();
    }

    private function episodeWithAudio(string $content, array $overrides = []): PodcastEpisode
    {
        Storage::fake('local');
        $path = 'media/audio/podcast-player-test-'.uniqid().'.mp3';
        Storage::disk('local')->put($path, $content);

        $media = Media::query()->create([
            'disk' => 'local',
            'path' => $path,
            'original_filename' => 'episode.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => strlen($content),
            'visibility' => 'protected',
        ]);

        $podcast = $this->createPodcast();

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Player Test Episode',
            'slug' => 'player-test-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
            'audio_media_id' => $media->id,
            ...$overrides,
        ]);
    }

    private function createPodcast(): Podcast
    {
        return Podcast::query()->create([
            'title' => 'Player Test Podcast '.uniqid(),
            'slug' => 'player-test-podcast-'.uniqid(),
            'status' => PodcastStatus::Published,
        ]);
    }
}
