<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Filament\Resources\Tracks\Pages\EditTrack;
use App\Modules\Music\Filament\Resources\Tracks\Pages\ListTracks;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
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
 * The temporary client-presentation switch (config('admin_ui.show_video_fields'),
 * ADMIN_SHOW_VIDEO_FIELDS) — proves the flag actually controls Video's UI
 * visibility on Track/PodcastEpisode Create/Edit forms and list tables, that
 * Audio is never affected by it, and that the underlying video_media_id
 * value survives a save made while the field is hidden. This is UI-only:
 * see TrackVideoPlayerTest/PodcastEpisodeVideoPlayerTest/
 * TrackMediaIndependenceTest/PodcastEpisodeMediaIndependenceTest for proof
 * the Video functionality itself (storage, streaming, independence) is
 * untouched — those run with the flag forced true (phpunit.xml).
 */
class VideoFieldPresentationFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_track_edit_form_hides_video_when_flag_disabled(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $track = $this->createTrackWithVideo();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->assertDontSee('Video File')
            ->assertDontSee(route('media.stream', $track->video), false);
    }

    public function test_track_edit_form_shows_video_when_flag_enabled(): void
    {
        config(['admin_ui.show_video_fields' => true]);
        $track = $this->createTrackWithVideo();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->assertSee('Video File')
            ->assertSee(route('media.stream', $track->video), false);
    }

    public function test_track_list_hides_video_column_when_flag_disabled(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $track = $this->createTrackWithVideo();

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->assertDontSee(route('media.stream', $track->video), false)
            ->assertDontSee('No video');
    }

    public function test_track_form_and_list_still_show_audio_when_video_flag_disabled(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $track = $this->createTrackWithAudioAndVideo();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->assertSee('Audio File')
            ->assertSee(route('media.stream', $track->audio), false);

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->assertSee(route('media.stream', $track->audio), false);
    }

    public function test_saving_the_track_edit_form_while_video_is_hidden_does_not_clear_existing_video(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $track = $this->createTrackWithVideo();
        $originalVideoId = $track->video_media_id;

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->fillForm(['title' => 'Updated While Video Hidden'])
            ->call('save')
            ->assertHasNoFormErrors();

        $track->refresh();
        $this->assertSame('Updated While Video Hidden', $track->title);
        $this->assertSame($originalVideoId, $track->video_media_id);
    }

    public function test_episode_edit_form_hides_video_when_flag_disabled(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $episode = $this->createEpisodeWithVideo();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertDontSee('Video File')
            ->assertDontSee(route('media.stream', $episode->video), false);
    }

    public function test_episode_edit_form_shows_video_when_flag_enabled(): void
    {
        config(['admin_ui.show_video_fields' => true]);
        $episode = $this->createEpisodeWithVideo();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertSee('Video File')
            ->assertSee(route('media.stream', $episode->video), false);
    }

    public function test_episode_list_hides_video_column_when_flag_disabled(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $episode = $this->createEpisodeWithVideo();

        Livewire::actingAs($this->admin())
            ->test(ListPodcastEpisodes::class)
            ->assertDontSee(route('media.stream', $episode->video), false)
            ->assertDontSee('No video');
    }

    public function test_episode_form_and_list_still_show_audio_when_video_flag_disabled(): void
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

    public function test_saving_the_episode_edit_form_while_video_is_hidden_does_not_clear_existing_video(): void
    {
        config(['admin_ui.show_video_fields' => false]);
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

    private function createTrackWithVideo(): Track
    {
        Storage::fake('local');
        $single = $this->createSingle();

        $video = $this->createMedia('video/mp4', 'media/video', 'flag-test.mp4');

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Flag Test Track',
            'slug' => 'flag-test-track-'.uniqid(),
            'video_media_id' => $video->id,
        ]);
    }

    private function createTrackWithAudioAndVideo(): Track
    {
        Storage::fake('local');
        $single = $this->createSingle();

        $audio = $this->createMedia('audio/mpeg', 'media/audio', 'flag-test.mp3');
        $video = $this->createMedia('video/mp4', 'media/video', 'flag-test.mp4');

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Flag Test Track Both',
            'slug' => 'flag-test-track-both-'.uniqid(),
            'audio_media_id' => $audio->id,
            'video_media_id' => $video->id,
        ]);
    }

    private function createEpisodeWithVideo(): PodcastEpisode
    {
        Storage::fake('local');
        $podcast = $this->createPodcast();

        $video = $this->createMedia('video/mp4', 'media/video', 'flag-test-episode.mp4');

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Flag Test Episode',
            'slug' => 'flag-test-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
            'video_media_id' => $video->id,
        ]);
    }

    private function createEpisodeWithAudioAndVideo(): PodcastEpisode
    {
        Storage::fake('local');
        $podcast = $this->createPodcast();

        $audio = $this->createMedia('audio/mpeg', 'media/audio', 'flag-test-episode.mp3');
        $video = $this->createMedia('video/mp4', 'media/video', 'flag-test-episode.mp4');

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Flag Test Episode Both',
            'slug' => 'flag-test-episode-both-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
            'audio_media_id' => $audio->id,
            'video_media_id' => $video->id,
        ]);
    }

    private function createMedia(string $mimeType, string $directory, string $filename): Media
    {
        $path = $directory.'/'.uniqid().'-'.$filename;
        Storage::disk('local')->put($path, 'fake-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = $filename;
        $media->mime_type = $mimeType;
        $media->size = 11;
        $media->visibility = 'protected';
        $media->save();

        return $media;
    }

    private function createSingle(): Single
    {
        return Single::query()->create([
            'title' => 'Flag Test Single '.uniqid(),
            'slug' => 'flag-test-single-'.uniqid(),
            'status' => ReleaseStatus::Draft,
        ]);
    }

    private function createPodcast(): Podcast
    {
        return Podcast::query()->create([
            'title' => 'Flag Test Podcast '.uniqid(),
            'slug' => 'flag-test-podcast-'.uniqid(),
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
