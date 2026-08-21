<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\EditPodcastEpisode;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies the PodcastEpisode media architecture — the video counterpart to
 * TrackMediaIndependenceTest, same guarantee: audio_media_id and
 * video_media_id are two fully independent MediaPicker fields (see
 * PodcastEpisodeForm), each its own Fieldset/Hidden/Actions group keyed by
 * its own field name, so uploading/selecting/clearing one can never touch
 * the other.
 */
class PodcastEpisodeMediaIndependenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_episode_can_have_audio_only(): void
    {
        $audio = $this->createMedia('audio/mpeg', 'media/audio');
        $episode = $this->createEpisode(['audio_media_id' => $audio->id]);

        $this->assertNotNull($episode->audio);
        $this->assertNull($episode->video);
    }

    public function test_an_episode_can_have_video_only(): void
    {
        $video = $this->createMedia('video/mp4', 'media/video');
        $episode = $this->createEpisode(['video_media_id' => $video->id]);

        $this->assertNull($episode->audio);
        $this->assertNotNull($episode->video);
    }

    public function test_an_episode_can_have_both_audio_and_video(): void
    {
        $audio = $this->createMedia('audio/mpeg', 'media/audio');
        $video = $this->createMedia('video/mp4', 'media/video');
        $episode = $this->createEpisode(['audio_media_id' => $audio->id, 'video_media_id' => $video->id]);

        $this->assertNotNull($episode->audio);
        $this->assertNotNull($episode->video);
        $this->assertNotSame($episode->audio->id, $episode->video->id);
    }

    public function test_an_episode_can_have_neither_audio_nor_video(): void
    {
        $episode = $this->createEpisode();

        $this->assertNull($episode->audio);
        $this->assertNull($episode->video);
    }

    public function test_uploading_audio_through_the_edit_form_does_not_change_an_existing_video(): void
    {
        Storage::fake('local');

        $video = $this->createMedia('video/mp4', 'media/video');
        $episode = $this->createEpisode(['video_media_id' => $video->id]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_upload', data: [
                'file' => UploadedFile::fake()->create('new-episode.mp3', 500, 'audio/mpeg'),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertSame($video->id, $episode->video_media_id);
        $this->assertNotNull($episode->audio_media_id);
        $this->assertNotSame($video->id, $episode->audio_media_id);
    }

    public function test_uploading_video_through_the_edit_form_does_not_change_an_existing_audio(): void
    {
        Storage::fake('local');

        $audio = $this->createMedia('audio/mpeg', 'media/audio');
        $episode = $this->createEpisode(['audio_media_id' => $audio->id]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->callFormComponentAction('video_media_id__actions', 'video_media_id_upload', data: [
                'file' => UploadedFile::fake()->create('new-episode.mp4', 2000, 'video/mp4'),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertSame($audio->id, $episode->audio_media_id);
        $this->assertNotNull($episode->video_media_id);
        $this->assertNotSame($audio->id, $episode->video_media_id);
    }

    public function test_clearing_audio_through_the_edit_form_does_not_remove_video(): void
    {
        $audio = $this->createMedia('audio/mpeg', 'media/audio');
        $video = $this->createMedia('video/mp4', 'media/video');
        $episode = $this->createEpisode(['audio_media_id' => $audio->id, 'video_media_id' => $video->id]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_clear')
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertNull($episode->audio_media_id);
        $this->assertSame($video->id, $episode->video_media_id);
    }

    public function test_clearing_video_through_the_edit_form_does_not_remove_audio(): void
    {
        $audio = $this->createMedia('audio/mpeg', 'media/audio');
        $video = $this->createMedia('video/mp4', 'media/video');
        $episode = $this->createEpisode(['audio_media_id' => $audio->id, 'video_media_id' => $video->id]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->callFormComponentAction('video_media_id__actions', 'video_media_id_clear')
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertSame($audio->id, $episode->audio_media_id);
        $this->assertNull($episode->video_media_id);
    }

    public function test_selecting_an_existing_audio_asset_does_not_duplicate_the_media_record(): void
    {
        $existingAudio = $this->createMedia('audio/mpeg', 'media/audio');
        $episode = $this->createEpisode();
        $countBefore = Media::query()->count();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_select', data: [
                'media' => $existingAudio->id,
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($countBefore, Media::query()->count());
        $this->assertSame($existingAudio->id, $episode->refresh()->audio_media_id);
    }

    public function test_selecting_an_existing_video_asset_does_not_duplicate_the_media_record(): void
    {
        $existingVideo = $this->createMedia('video/mp4', 'media/video');
        $episode = $this->createEpisode();
        $countBefore = Media::query()->count();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->callFormComponentAction('video_media_id__actions', 'video_media_id_select', data: [
                'media' => $existingVideo->id,
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($countBefore, Media::query()->count());
        $this->assertSame($existingVideo->id, $episode->refresh()->video_media_id);
    }

    private function createMedia(string $mimeType, string $directory): Media
    {
        Storage::fake('local');

        $path = $directory.'/'.uniqid().'.bin';
        Storage::disk('local')->put($path, 'fake-bytes');

        $media = new Media;
        $media->disk = 'local';
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
    private function createEpisode(array $overrides = []): PodcastEpisode
    {
        $podcast = Podcast::query()->create([
            'title' => 'Media Independence Test Podcast '.uniqid(),
            'slug' => 'media-independence-podcast-'.uniqid(),
            'status' => PodcastStatus::Draft,
        ]);

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Media Independence Test Episode',
            'slug' => 'media-independence-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Draft,
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
