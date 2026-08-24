<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\CreatePodcastEpisode;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\EditPodcastEpisode;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\ListPodcastEpisodes;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Client-confirmed 2026-08-24: Video is a permanent, confirmed-rejected
 * feature for Podcast Episodes — not a presentation-mode setting. Unlike
 * Track (see VideoFieldPresentationFlagTest), the Video MediaPicker field
 * and "Video" preview column are removed outright from
 * PodcastEpisodeForm/PodcastEpisodesTable and never respond to
 * config('admin_ui.show_video_fields')/ADMIN_SHOW_VIDEO_FIELDS — every test
 * here proves that under both flag states. video_media_id and the video()
 * relation stay on the model/DB (PodcastEpisodeMediaIndependenceTest covers
 * that layer); this file is UI-only.
 */
class PodcastEpisodeVideoHiddenTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_never_shows_video_when_flag_disabled(): void
    {
        config(['admin_ui.show_video_fields' => false]);

        Livewire::actingAs($this->admin())
            ->test(CreatePodcastEpisode::class)
            ->assertDontSee('Video File');
    }

    public function test_create_form_never_shows_video_when_flag_enabled(): void
    {
        config(['admin_ui.show_video_fields' => true]);

        Livewire::actingAs($this->admin())
            ->test(CreatePodcastEpisode::class)
            ->assertDontSee('Video File');
    }

    public function test_edit_form_never_shows_video_when_flag_disabled(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $episode = $this->createEpisodeWithVideo();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertDontSee('Video File')
            ->assertDontSee(route('media.stream', $episode->video), false);
    }

    public function test_edit_form_never_shows_video_when_flag_enabled(): void
    {
        config(['admin_ui.show_video_fields' => true]);
        $episode = $this->createEpisodeWithVideo();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertDontSee('Video File')
            ->assertDontSee(route('media.stream', $episode->video), false);
    }

    public function test_list_never_shows_video_column_when_flag_disabled(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $episode = $this->createEpisodeWithVideo();

        Livewire::actingAs($this->admin())
            ->test(ListPodcastEpisodes::class)
            ->assertDontSee(route('media.stream', $episode->video), false)
            ->assertDontSee('No video')
            ->assertDontSeeHtml('<video');
    }

    public function test_list_never_shows_video_column_when_flag_enabled(): void
    {
        config(['admin_ui.show_video_fields' => true]);
        $episode = $this->createEpisodeWithVideo();

        Livewire::actingAs($this->admin())
            ->test(ListPodcastEpisodes::class)
            ->assertDontSee(route('media.stream', $episode->video), false)
            ->assertDontSee('No video')
            ->assertDontSeeHtml('<video');
    }

    public function test_audio_still_shows_on_edit_and_list_regardless_of_flag(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $episode = $this->createEpisodeWithAudioAndVideo();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertSee('Audio File')
            ->assertSee(route('media.stream', $episode->audio), false);

        Livewire::actingAs($this->admin())
            ->test(ListPodcastEpisodes::class)
            ->assertSee(route('media.stream', $episode->audio), false);
    }

    public function test_saving_the_episode_edit_form_does_not_clear_existing_video(): void
    {
        $episode = $this->createEpisodeWithVideo();
        $originalVideoId = $episode->video_media_id;

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->fillForm(['title' => 'Updated While Video Hidden'])
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertSame('Updated While Video Hidden', $episode->title);
        $this->assertSame($originalVideoId, $episode->video_media_id);
    }

    private function createEpisodeWithVideo(): PodcastEpisode
    {
        Storage::fake('local');
        $podcast = $this->createPodcast();

        $path = 'media/video/episode-hidden-test-'.uniqid().'.mp4';
        Storage::disk('local')->put($path, 'fake-mp4-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = 'episode-hidden-test.mp4';
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

    private function createEpisodeWithAudioAndVideo(): PodcastEpisode
    {
        Storage::fake('local');
        $podcast = $this->createPodcast();

        $audioPath = 'media/audio/episode-hidden-test-'.uniqid().'.mp3';
        Storage::disk('local')->put($audioPath, 'fake-mp3-bytes');
        $audio = new Media;
        $audio->disk = 'local';
        $audio->path = $audioPath;
        $audio->original_filename = 'episode-hidden-test.mp3';
        $audio->mime_type = 'audio/mpeg';
        $audio->size = 11;
        $audio->visibility = 'protected';
        $audio->save();

        $videoPath = 'media/video/episode-hidden-test-'.uniqid().'.mp4';
        Storage::disk('local')->put($videoPath, 'fake-mp4-bytes');
        $video = new Media;
        $video->disk = 'local';
        $video->path = $videoPath;
        $video->original_filename = 'episode-hidden-test.mp4';
        $video->mime_type = 'video/mp4';
        $video->size = 14;
        $video->visibility = 'protected';
        $video->save();

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Episode With Audio And Video',
            'slug' => 'episode-with-audio-and-video-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
            'audio_media_id' => $audio->id,
            'video_media_id' => $video->id,
        ]);
    }

    private function createPodcast(): Podcast
    {
        return Podcast::query()->create([
            'title' => 'Video Hidden Test Podcast '.uniqid(),
            'slug' => 'video-hidden-test-podcast-'.uniqid(),
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
