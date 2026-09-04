<?php

namespace Tests\Feature\Podcast;

use App\Models\Media;
use App\Models\User;
use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Commerce\Models\DownloadLog;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Podcast Episode downloads are free for any registered user — no Stripe,
 * Order, Subscription, or Entitlement anywhere in this flow — while a guest
 * is denied server-side by the `auth` route middleware itself (see
 * routes/web.php), not merely a hidden Blade button. Every outcome is
 * recorded in the same shared DownloadLog Music's downloads use.
 */
class PodcastEpisodeDownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login_and_never_receives_the_file(): void
    {
        $episode = $this->episodeWithAudio('audio-bytes');

        $response = $this->get(route('podcast.episodes.download', $episode));

        $response->assertRedirect(route('login'));
    }

    public function test_a_registered_user_can_download_for_free_with_no_subscription_or_purchase(): void
    {
        $episode = $this->episodeWithAudio('audio-bytes');
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get(route('podcast.episodes.download', $episode));

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_the_download_is_recorded_in_the_shared_download_history_as_free(): void
    {
        $episode = $this->episodeWithAudio('audio-bytes');
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get(route('podcast.episodes.download', $episode));

        $log = DownloadLog::query()->where('status', DownloadLogStatus::Succeeded)->first();
        $this->assertNotNull($log);
        $this->assertSame($user->getKey(), $log->user_id);
        $this->assertSame($episode->getKey(), $log->podcast_episode_id);
        $this->assertNull($log->track_id);
        $this->assertNull($log->entitlement_id);
        $this->assertSame(DownloadAccessType::Free, $log->access_type);
    }

    public function test_a_registered_user_is_denied_when_the_episode_has_no_audio_file(): void
    {
        $podcast = $this->createPodcast();
        $episode = PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'No Audio',
            'slug' => 'no-audio-download-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
        ]);
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->from(route('podcast.episodes.show', $episode))
            ->get(route('podcast.episodes.download', $episode));

        $response->assertRedirect(route('podcast.episodes.show', $episode));
        $response->assertSessionHas('download_error');
    }

    /**
     * The "Download Audio" link was removed from the episode page
     * (client-confirmed, 2026-09-05) in favor of the on-page audio player —
     * see PodcastEpisodeAudioPlayerTest for the player's own coverage. The
     * backend download route/controller above are left fully intact; this
     * only confirms the page itself no longer links to it, for any visitor.
     */
    public function test_the_episode_page_no_longer_shows_a_download_link_for_a_registered_user(): void
    {
        $episode = $this->episodeWithAudio('audio-bytes');
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get(route('podcast.episodes.show', $episode));

        $response->assertDontSee(route('podcast.episodes.download', $episode), false);
        $response->assertDontSee('Download Audio');
    }

    public function test_the_episode_page_does_not_show_a_download_link_for_a_guest(): void
    {
        $episode = $this->episodeWithAudio('audio-bytes');

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertDontSee(route('podcast.episodes.download', $episode), false);
        $response->assertDontSee('Download Audio');
    }

    private function episodeWithAudio(string $content, array $overrides = []): PodcastEpisode
    {
        Storage::fake('local');
        $path = 'media/audio/podcast-download-test-'.uniqid().'.mp3';
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
            'title' => 'Download Test Episode',
            'slug' => 'download-test-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
            'audio_media_id' => $media->id,
            ...$overrides,
        ]);
    }

    private function createPodcast(): Podcast
    {
        return Podcast::query()->create([
            'title' => 'Download Test Podcast '.uniqid(),
            'slug' => 'download-test-podcast-'.uniqid(),
            'status' => PodcastStatus::Published,
        ]);
    }
}
