<?php

namespace Tests\Feature\Admin;

use App\Filament\Support\Media\MediaPreview;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\EditPodcastEpisode;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\ListPodcastEpisodes;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin-only video player/preview for Podcast Episodes — the video
 * counterpart to PodcastEpisodeAudioPlayerTest, applied to
 * PodcastEpisode's new video_media_id. `embed_url` remains a separate,
 * external-only streaming reference and is never used as this source.
 */
class PodcastEpisodeVideoPlayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_episode_list_shows_a_video_player_for_an_episode_with_video(): void
    {
        $episode = $this->createEpisodeWithVideo();

        Livewire::actingAs($this->admin())
            ->test(ListPodcastEpisodes::class)
            ->assertSee(route('media.stream', $episode->video), false);
    }

    public function test_the_episode_list_shows_a_no_video_state_for_an_episode_without_video(): void
    {
        $this->createEpisodeWithoutVideo();

        Livewire::actingAs($this->admin())
            ->test(ListPodcastEpisodes::class)
            ->assertSee('No video')
            ->assertDontSee('<video', false);
    }

    public function test_the_episode_edit_page_shows_the_video_player_when_video_is_set(): void
    {
        $episode = $this->createEpisodeWithVideo();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertSee(route('media.stream', $episode->video), false);
    }

    public function test_the_episode_edit_page_shows_a_no_video_state_when_video_is_not_set(): void
    {
        $episode = $this->createEpisodeWithoutVideo();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertSee('No Video selected.');
    }

    public function test_an_authorized_admin_can_stream_the_previewed_episode_video(): void
    {
        $episode = $this->createEpisodeWithVideo();

        $response = $this->actingAs($this->admin())->get(route('media.stream', $episode->video));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'video/mp4');
    }

    public function test_an_unauthenticated_guest_cannot_stream_the_previewed_episode_video(): void
    {
        $episode = $this->createEpisodeWithVideo();

        $response = $this->get(route('media.stream', $episode->video));

        $response->assertForbidden();
    }

    public function test_a_non_admin_cannot_stream_the_previewed_episode_video(): void
    {
        $episode = $this->createEpisodeWithVideo();
        $nonAdmin = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($nonAdmin)->get(route('media.stream', $episode->video));

        $response->assertForbidden();
    }

    public function test_the_player_url_never_exposes_the_physical_storage_path(): void
    {
        $episode = $this->createEpisodeWithVideo();

        $html = (string) MediaPreview::videoPlayer($episode->video);

        $this->assertStringContainsString(route('media.stream', $episode->video), $html);
        $this->assertStringNotContainsString($episode->video->path, $html);
        $this->assertStringNotContainsString('/storage/', $html);
    }

    private function createEpisodeWithVideo(): PodcastEpisode
    {
        Storage::fake('local');

        $podcast = $this->createPodcast();

        $path = 'media/video/episode-player-test-'.uniqid().'.mp4';
        Storage::disk('local')->put($path, 'fake-mp4-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = 'episode-player-test.mp4';
        $media->mime_type = 'video/mp4';
        $media->size = 14;
        $media->visibility = 'protected';
        $media->save();

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Episode With Video',
            'slug' => 'episode-with-video-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
            'video_media_id' => $media->id,
        ]);
    }

    private function createEpisodeWithoutVideo(): PodcastEpisode
    {
        $podcast = $this->createPodcast();

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Episode Without Video',
            'slug' => 'episode-without-video-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
        ]);
    }

    private function createPodcast(): Podcast
    {
        return Podcast::query()->create([
            'title' => 'Video Player Test Podcast '.uniqid(),
            'slug' => 'video-player-test-podcast-'.uniqid(),
            'status' => PodcastStatus::Draft,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
