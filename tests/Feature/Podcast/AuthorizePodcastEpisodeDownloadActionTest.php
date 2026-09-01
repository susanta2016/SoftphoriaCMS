<?php

namespace Tests\Feature\Podcast;

use App\Models\Media;
use App\Models\User;
use App\Modules\Commerce\Enums\DownloadAccessType;
use App\Modules\Commerce\Enums\DownloadLogStatus;
use App\Modules\Commerce\Models\DownloadLog;
use App\Modules\Podcast\Actions\Download\AuthorizePodcastEpisodeDownloadAction;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Corrected rule (2026-09-01): a Podcast Episode download is free for any
 * registered user — no Subscription/Entitlement/purchase, superseding this
 * action's earlier "active Pro Member only" rule (see its own docblock for
 * why: Member Subscription defaults to disabled in Phase 1, which would have
 * made every Podcast download permanently unreachable under the old rule).
 * Every outcome, success or denial, is written to the same shared DownloadLog
 * Music's downloads use — no parallel download-history mechanism.
 */
class AuthorizePodcastEpisodeDownloadActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_registered_user_is_authorized_with_no_subscription_or_purchase_of_any_kind(): void
    {
        $episode = $this->episodeWithAudio();
        $user = $this->member();

        $result = app(AuthorizePodcastEpisodeDownloadAction::class)->authorizeForUser($episode, $user);

        $this->assertTrue($result->authorized);
        $this->assertSame(DownloadAccessType::Free, $result->accessType);
        $this->assertNotNull($result->media);
    }

    public function test_the_successful_download_is_recorded_in_the_shared_download_log(): void
    {
        $episode = $this->episodeWithAudio();
        $user = $this->member();

        app(AuthorizePodcastEpisodeDownloadAction::class)->authorizeForUser($episode, $user, '203.0.113.5', 'PHPUnit');

        $log = DownloadLog::query()->where('status', DownloadLogStatus::Succeeded)->first();
        $this->assertNotNull($log);
        $this->assertSame($user->getKey(), $log->user_id);
        $this->assertSame($episode->getKey(), $log->podcast_episode_id);
        $this->assertNull($log->track_id);
        $this->assertNull($log->entitlement_id);
        $this->assertSame(DownloadAccessType::Free, $log->access_type);
        $this->assertSame('203.0.113.5', $log->ip_address);
    }

    public function test_a_user_is_denied_when_the_episode_has_no_audio_asset(): void
    {
        $podcast = $this->createPodcast();
        $episode = PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'No Audio Episode',
            'slug' => 'no-audio-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
        ]);
        $user = $this->member();

        $result = app(AuthorizePodcastEpisodeDownloadAction::class)->authorizeForUser($episode, $user);

        $this->assertFalse($result->authorized);
        $this->assertSame('no_audio_asset', $result->denialReason);

        $log = DownloadLog::query()->where('status', DownloadLogStatus::Denied)->first();
        $this->assertNotNull($log);
        $this->assertSame('no_audio_asset', $log->denial_reason);
        $this->assertNull($log->access_type);
    }

    private function episodeWithAudio(): PodcastEpisode
    {
        $podcast = $this->createPodcast();

        $audio = new Media;
        $audio->disk = 'local';
        $audio->path = 'media/audio/'.uniqid().'.mp3';
        $audio->original_filename = 'download-test.mp3';
        $audio->mime_type = 'audio/mpeg';
        $audio->size = 1024;
        $audio->visibility = 'protected';
        $audio->save();

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Download Auth Test Episode',
            'slug' => 'download-auth-test-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
            'audio_media_id' => $audio->id,
        ]);
    }

    private function createPodcast(): Podcast
    {
        return Podcast::query()->create([
            'title' => 'Download Auth Test Podcast '.uniqid(),
            'slug' => 'download-auth-test-podcast-'.uniqid(),
            'status' => PodcastStatus::Published,
        ]);
    }

    private function member(): User
    {
        return User::factory()->create(['status' => 'active']);
    }
}
