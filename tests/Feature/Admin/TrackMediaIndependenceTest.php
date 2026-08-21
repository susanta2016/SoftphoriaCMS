<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Filament\Resources\Tracks\Pages\EditTrack;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies the Track media architecture: audio_media_id and video_media_id
 * are two fully independent MediaPicker fields (see TrackForm) — each its
 * own Fieldset/Hidden/Actions group keyed by its own field name (see
 * MediaPicker::make()'s structure), so there is no code path by which
 * uploading, selecting, or clearing one can touch the other. This proves
 * that guarantee empirically through the real admin form, and that
 * selecting an existing Media Library asset never creates a duplicate row.
 */
class TrackMediaIndependenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_track_can_have_audio_only(): void
    {
        $audio = $this->createMedia('audio/mpeg', 'local', 'media/audio');
        $track = $this->createTrack(['audio_media_id' => $audio->id]);

        $this->assertNotNull($track->audio);
        $this->assertNull($track->video);
    }

    public function test_a_track_can_have_video_only(): void
    {
        $video = $this->createMedia('video/mp4', 'local', 'media/video');
        $track = $this->createTrack(['video_media_id' => $video->id]);

        $this->assertNull($track->audio);
        $this->assertNotNull($track->video);
    }

    public function test_a_track_can_have_both_audio_and_video(): void
    {
        $audio = $this->createMedia('audio/mpeg', 'local', 'media/audio');
        $video = $this->createMedia('video/mp4', 'local', 'media/video');
        $track = $this->createTrack(['audio_media_id' => $audio->id, 'video_media_id' => $video->id]);

        $this->assertNotNull($track->audio);
        $this->assertNotNull($track->video);
        $this->assertNotSame($track->audio->id, $track->video->id);
    }

    public function test_a_track_can_have_neither_audio_nor_video(): void
    {
        $track = $this->createTrack();

        $this->assertNull($track->audio);
        $this->assertNull($track->video);
    }

    public function test_uploading_audio_through_the_edit_form_does_not_change_an_existing_video(): void
    {
        Storage::fake('local');

        $video = $this->createMedia('video/mp4', 'local', 'media/video');
        $track = $this->createTrack(['video_media_id' => $video->id]);

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_upload', data: [
                'file' => UploadedFile::fake()->create('new-song.mp3', 500, 'audio/mpeg'),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $track->refresh();
        $this->assertSame($video->id, $track->video_media_id);
        $this->assertNotNull($track->audio_media_id);
        $this->assertNotSame($video->id, $track->audio_media_id);
    }

    public function test_uploading_video_through_the_edit_form_does_not_change_an_existing_audio(): void
    {
        Storage::fake('local');

        $audio = $this->createMedia('audio/mpeg', 'local', 'media/audio');
        $track = $this->createTrack(['audio_media_id' => $audio->id]);

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->callFormComponentAction('video_media_id__actions', 'video_media_id_upload', data: [
                'file' => UploadedFile::fake()->create('new-clip.mp4', 2000, 'video/mp4'),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $track->refresh();
        $this->assertSame($audio->id, $track->audio_media_id);
        $this->assertNotNull($track->video_media_id);
        $this->assertNotSame($audio->id, $track->video_media_id);
    }

    public function test_clearing_audio_through_the_edit_form_does_not_remove_video(): void
    {
        $audio = $this->createMedia('audio/mpeg', 'local', 'media/audio');
        $video = $this->createMedia('video/mp4', 'local', 'media/video');
        $track = $this->createTrack(['audio_media_id' => $audio->id, 'video_media_id' => $video->id]);

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_clear')
            ->call('save')
            ->assertHasNoFormErrors();

        $track->refresh();
        $this->assertNull($track->audio_media_id);
        $this->assertSame($video->id, $track->video_media_id);
    }

    public function test_clearing_video_through_the_edit_form_does_not_remove_audio(): void
    {
        $audio = $this->createMedia('audio/mpeg', 'local', 'media/audio');
        $video = $this->createMedia('video/mp4', 'local', 'media/video');
        $track = $this->createTrack(['audio_media_id' => $audio->id, 'video_media_id' => $video->id]);

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->callFormComponentAction('video_media_id__actions', 'video_media_id_clear')
            ->call('save')
            ->assertHasNoFormErrors();

        $track->refresh();
        $this->assertSame($audio->id, $track->audio_media_id);
        $this->assertNull($track->video_media_id);
    }

    public function test_selecting_an_existing_audio_asset_does_not_duplicate_the_media_record(): void
    {
        $existingAudio = $this->createMedia('audio/mpeg', 'local', 'media/audio');
        $track = $this->createTrack();
        $countBefore = Media::query()->count();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_select', data: [
                'media' => $existingAudio->id,
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($countBefore, Media::query()->count());
        $this->assertSame($existingAudio->id, $track->refresh()->audio_media_id);
    }

    public function test_selecting_an_existing_video_asset_does_not_duplicate_the_media_record(): void
    {
        $existingVideo = $this->createMedia('video/mp4', 'local', 'media/video');
        $track = $this->createTrack();
        $countBefore = Media::query()->count();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->callFormComponentAction('video_media_id__actions', 'video_media_id_select', data: [
                'media' => $existingVideo->id,
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($countBefore, Media::query()->count());
        $this->assertSame($existingVideo->id, $track->refresh()->video_media_id);
    }

    private function createMedia(string $mimeType, string $disk, string $directory): Media
    {
        Storage::fake($disk);

        $path = $directory.'/'.uniqid().'.bin';
        Storage::disk($disk)->put($path, 'fake-bytes');

        $media = new Media;
        $media->disk = $disk;
        $media->path = $path;
        $media->original_filename = basename($path);
        $media->mime_type = $mimeType;
        $media->size = 11;
        $media->visibility = 'protected';
        $media->save();

        return $media;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTrack(array $overrides = []): Track
    {
        $single = Single::query()->create([
            'title' => 'Media Independence Test Single '.uniqid(),
            'slug' => 'media-independence-single-'.uniqid(),
            'status' => ReleaseStatus::Draft,
        ]);

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Media Independence Test Track',
            'slug' => 'media-independence-track-'.uniqid(),
            ...$overrides,
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
