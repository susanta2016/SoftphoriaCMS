<?php

namespace Tests\Feature\Podcast;

use App\Models\Media;
use App\Models\User;
use App\Modules\Commerce\Enums\SubscriptionStatus;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Podcast\Actions\Download\AuthorizePodcastEpisodeDownloadAction;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client-confirmed rule (2026-08-24): only an active, paid Pro Member may
 * download a Podcast Episode's audio — free Members and guests never get
 * download access merely because the episode is available/streamable, and
 * the existing Pro subscription status/period rules (a cancelled
 * subscription stays active until its already-paid period ends — see
 * App\Modules\Commerce\Models\Subscription::isActive()) govern eligibility
 * exactly as they do for Music, via the same
 * User::hasActiveMembership() this Action reuses read-only. Mirrors
 * SubscriptionCancellationTest's Music-side coverage of the identical
 * period-end rule.
 */
class AuthorizePodcastEpisodeDownloadActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_denied(): void
    {
        $episode = $this->episodeWithAudio();

        $authorized = app(AuthorizePodcastEpisodeDownloadAction::class)->authorize($episode, null);

        $this->assertFalse($authorized);
    }

    public function test_free_member_without_a_subscription_is_denied(): void
    {
        $episode = $this->episodeWithAudio();
        $user = $this->member();

        $authorized = app(AuthorizePodcastEpisodeDownloadAction::class)->authorize($episode, $user);

        $this->assertFalse($authorized);
    }

    public function test_active_pro_subscriber_is_authorized(): void
    {
        $episode = $this->episodeWithAudio();
        $user = $this->member();

        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
            'cancel_at_period_end' => false,
        ]);

        $authorized = app(AuthorizePodcastEpisodeDownloadAction::class)->authorize($episode, $user);

        $this->assertTrue($authorized);
    }

    public function test_a_cancelled_subscription_still_authorized_during_the_remaining_paid_period(): void
    {
        $episode = $this->episodeWithAudio();
        $user = $this->member();

        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(10),
            'cancel_at_period_end' => true,
            'cancelled_at' => now(),
        ]);

        $authorized = app(AuthorizePodcastEpisodeDownloadAction::class)->authorize($episode, $user);

        $this->assertTrue($authorized);
    }

    public function test_access_stops_once_the_paid_period_has_actually_ended(): void
    {
        $episode = $this->episodeWithAudio();
        $user = $this->member();

        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->subDay(),
            'cancel_at_period_end' => true,
            'cancelled_at' => now()->subDays(30),
        ]);

        $authorized = app(AuthorizePodcastEpisodeDownloadAction::class)->authorize($episode, $user);

        $this->assertFalse($authorized);
    }

    public function test_access_stops_once_stripe_reports_the_subscription_fully_ended(): void
    {
        $episode = $this->episodeWithAudio();
        $user = $this->member();

        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Canceled,
            'current_period_end' => now()->addDays(5),
            'ended_at' => now(),
        ]);

        $authorized = app(AuthorizePodcastEpisodeDownloadAction::class)->authorize($episode, $user);

        $this->assertFalse($authorized);
    }

    public function test_active_pro_subscriber_is_still_denied_when_the_episode_has_no_audio_asset(): void
    {
        $podcast = $this->createPodcast();
        $episode = PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'No Audio Episode',
            'slug' => 'no-audio-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
        ]);
        $user = $this->member();

        Subscription::query()->create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
            'cancel_at_period_end' => false,
        ]);

        $authorized = app(AuthorizePodcastEpisodeDownloadAction::class)->authorize($episode, $user);

        $this->assertFalse($authorized);
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
