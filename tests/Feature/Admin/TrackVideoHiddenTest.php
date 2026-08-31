<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Filament\Resources\Tracks\Pages\CreateTrack;
use App\Modules\Music\Filament\Resources\Tracks\Pages\EditTrack;
use App\Modules\Music\Filament\Resources\Tracks\Pages\ListTracks;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Client-confirmed 2026-08-31: Track's "Video File" option is a permanent
 * removal, not a presentation-mode setting (same treatment Podcast
 * Episode's Video already had — see PodcastEpisodeVideoHiddenTest, whose
 * shape this file mirrors). The Video MediaPicker field and "Video" preview
 * column are removed outright from TrackForm/TracksTable and never respond
 * to config('admin_ui.show_video_fields')/ADMIN_SHOW_VIDEO_FIELDS — every
 * test here proves that under both flag states. video_media_id and the
 * video() relation stay on the model/DB (TrackMediaIndependenceTest covers
 * that layer); this file is UI-only. The renamed "Audio File" label
 * (formerly "Downloadable Audio File") is also asserted here.
 */
class TrackVideoHiddenTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_never_shows_video_when_flag_disabled(): void
    {
        config(['admin_ui.show_video_fields' => false]);

        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->assertDontSee('Video File');
    }

    public function test_create_form_never_shows_video_when_flag_enabled(): void
    {
        config(['admin_ui.show_video_fields' => true]);

        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->assertDontSee('Video File');
    }

    public function test_edit_form_never_shows_video_when_flag_disabled(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $track = $this->createTrackWithVideo();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->assertDontSee('Video File')
            ->assertDontSee(route('media.stream', $track->video), false);
    }

    public function test_edit_form_never_shows_video_when_flag_enabled(): void
    {
        config(['admin_ui.show_video_fields' => true]);
        $track = $this->createTrackWithVideo();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->assertDontSee('Video File')
            ->assertDontSee(route('media.stream', $track->video), false);
    }

    public function test_list_never_shows_video_column_when_flag_disabled(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $track = $this->createTrackWithVideo();

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->assertDontSee(route('media.stream', $track->video), false)
            ->assertDontSee('No video')
            ->assertDontSeeHtml('<video');
    }

    public function test_list_never_shows_video_column_when_flag_enabled(): void
    {
        config(['admin_ui.show_video_fields' => true]);
        $track = $this->createTrackWithVideo();

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->assertDontSee(route('media.stream', $track->video), false)
            ->assertDontSee('No video')
            ->assertDontSeeHtml('<video');
    }

    public function test_audio_still_shows_with_its_renamed_label_regardless_of_flag(): void
    {
        config(['admin_ui.show_video_fields' => false]);
        $track = $this->createTrackWithAudioAndVideo();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->assertSee('Audio File')
            ->assertDontSee('Downloadable Audio File')
            ->assertSee(route('media.stream', $track->audio), false);

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->assertSee(route('media.stream', $track->audio), false);
    }

    public function test_saving_the_track_edit_form_does_not_clear_existing_video(): void
    {
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

    private function createTrackWithVideo(): Track
    {
        Storage::fake('local');
        $single = $this->createSingle();

        $path = 'media/video/track-video-hidden-test-'.uniqid().'.mp4';
        Storage::disk('local')->put($path, 'fake-mp4-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = 'track-video-hidden-test.mp4';
        $media->mime_type = 'video/mp4';
        $media->size = 14;
        $media->visibility = 'protected';
        $media->save();

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Track With Video',
            'slug' => 'track-with-video-'.uniqid(),
            'video_media_id' => $media->id,
        ]);
    }

    private function createTrackWithAudioAndVideo(): Track
    {
        Storage::fake('local');
        $single = $this->createSingle();

        $audioPath = 'media/audio/track-video-hidden-test-'.uniqid().'.mp3';
        Storage::disk('local')->put($audioPath, 'fake-mp3-bytes');
        $audio = new Media;
        $audio->disk = 'local';
        $audio->path = $audioPath;
        $audio->original_filename = 'track-video-hidden-test.mp3';
        $audio->mime_type = 'audio/mpeg';
        $audio->size = 11;
        $audio->visibility = 'protected';
        $audio->save();

        $videoPath = 'media/video/track-video-hidden-test-'.uniqid().'.mp4';
        Storage::disk('local')->put($videoPath, 'fake-mp4-bytes');
        $video = new Media;
        $video->disk = 'local';
        $video->path = $videoPath;
        $video->original_filename = 'track-video-hidden-test.mp4';
        $video->mime_type = 'video/mp4';
        $video->size = 14;
        $video->visibility = 'protected';
        $video->save();

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Track With Audio And Video',
            'slug' => 'track-with-audio-and-video-'.uniqid(),
            'audio_media_id' => $audio->id,
            'video_media_id' => $video->id,
        ]);
    }

    private function createSingle(): Single
    {
        return Single::query()->create([
            'title' => 'Video Hidden Test Single '.uniqid(),
            'slug' => 'video-hidden-test-single-'.uniqid(),
            'status' => ReleaseStatus::Draft,
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
