<?php

namespace Tests\Feature\Admin;

use App\Filament\Support\Media\MediaPreview;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Commerce\Models\DownloadLog;
use App\Modules\Commerce\Models\Entitlement;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Filament\Resources\Tracks\Pages\EditTrack;
use App\Modules\Music\Filament\Resources\Tracks\Pages\ListTracks;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin-only video player/preview for Tracks — the video counterpart to
 * TrackAudioPlayerTest, extending the same MediaPreview/media.stream
 * mechanism to MediaCategory::Video rather than introducing a second one.
 * `video_embed_url` (external, e.g. YouTube/Vimeo) stays completely
 * separate and is never used as this preview's source. Playback-only:
 * never touches Commerce (no entitlement, no download count, no download
 * log).
 */
class TrackVideoPlayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_track_list_shows_a_video_player_for_a_track_with_video(): void
    {
        $track = $this->createTrackWithVideo();

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->assertSee(route('media.stream', $track->video), false);
    }

    public function test_the_track_list_shows_a_no_video_state_for_a_track_without_video(): void
    {
        $this->createTrackWithoutVideo();

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->assertSee('No video')
            ->assertDontSee('<video', false);
    }

    public function test_the_track_edit_page_shows_the_video_player_when_video_is_set(): void
    {
        $track = $this->createTrackWithVideo();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->assertSee(route('media.stream', $track->video), false);
    }

    public function test_the_track_edit_page_shows_a_no_video_state_when_video_is_not_set(): void
    {
        $track = $this->createTrackWithoutVideo();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->assertSee('No Video selected.');
    }

    public function test_an_authorized_admin_can_stream_the_previewed_video(): void
    {
        $track = $this->createTrackWithVideo();

        $response = $this->actingAs($this->admin())->get(route('media.stream', $track->video));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'video/mp4');
    }

    public function test_an_unauthenticated_guest_cannot_stream_the_previewed_video(): void
    {
        $track = $this->createTrackWithVideo();

        $response = $this->get(route('media.stream', $track->video));

        $response->assertForbidden();
    }

    public function test_a_non_admin_cannot_stream_the_previewed_video(): void
    {
        $track = $this->createTrackWithVideo();
        $nonAdmin = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($nonAdmin)->get(route('media.stream', $track->video));

        $response->assertForbidden();
    }

    /**
     * The whole point of reusing StreamMediaController rather than a
     * download-authorization endpoint: playback must never touch Commerce
     * state, regardless of how many times an admin plays the file back.
     */
    public function test_previewing_video_does_not_create_or_change_any_commerce_entitlement_or_download_count(): void
    {
        $track = $this->createTrackWithVideo();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('media.stream', $track->video))->assertOk();
        $this->actingAs($admin)->get(route('media.stream', $track->video))->assertOk();
        $this->actingAs($admin)->get(route('media.stream', $track->video))->assertOk();

        $this->assertSame(0, Entitlement::query()->count());
        $this->assertSame(0, DownloadLog::query()->count());
    }

    /**
     * The player's <source src="..."> is always the application-controlled
     * stream route, never the physical disk path or a public /storage/...
     * URL — asserted directly against MediaPreview's actual output, the
     * single place this markup is generated.
     */
    public function test_the_player_url_never_exposes_the_physical_storage_path(): void
    {
        $track = $this->createTrackWithVideo();

        $html = (string) MediaPreview::videoPlayer($track->video);

        $this->assertStringContainsString(route('media.stream', $track->video), $html);
        $this->assertStringNotContainsString($track->video->path, $html);
        $this->assertStringNotContainsString('/storage/', $html);
    }

    /**
     * A Track can have audio and video at the same time — each preview is
     * resolved independently, neither one masks or substitutes the other.
     */
    public function test_a_track_can_have_both_an_audio_and_a_video_player_simultaneously(): void
    {
        Storage::fake('local');
        $single = $this->createSingle();

        $audioPath = 'media/audio/both-'.uniqid().'.mp3';
        Storage::disk('local')->put($audioPath, 'fake-mp3-bytes');
        $audio = new Media;
        $audio->disk = 'local';
        $audio->path = $audioPath;
        $audio->original_filename = 'both.mp3';
        $audio->mime_type = 'audio/mpeg';
        $audio->size = 14;
        $audio->visibility = 'protected';
        $audio->save();

        $videoPath = 'media/video/both-'.uniqid().'.mp4';
        Storage::disk('local')->put($videoPath, 'fake-mp4-bytes');
        $video = new Media;
        $video->disk = 'local';
        $video->path = $videoPath;
        $video->original_filename = 'both.mp4';
        $video->mime_type = 'video/mp4';
        $video->size = 14;
        $video->visibility = 'protected';
        $video->save();

        $track = Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Both Media Track',
            'slug' => 'both-media-track-'.uniqid(),
            'audio_media_id' => $audio->id,
            'video_media_id' => $video->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->assertSee(route('media.stream', $track->audio), false)
            ->assertSee(route('media.stream', $track->video), false);
    }

    private function createTrackWithVideo(): Track
    {
        Storage::fake('local');

        $single = $this->createSingle();

        $path = 'media/video/player-test-'.uniqid().'.mp4';
        Storage::disk('local')->put($path, 'fake-mp4-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = 'player-test.mp4';
        $media->mime_type = 'video/mp4';
        $media->size = 14;
        $media->visibility = 'protected';
        $media->save();

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Video Player Test Track',
            'slug' => 'video-player-test-track-'.uniqid(),
            'video_media_id' => $media->id,
        ]);
    }

    private function createTrackWithoutVideo(): Track
    {
        $single = $this->createSingle();

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'No Video Track',
            'slug' => 'no-video-track-'.uniqid(),
        ]);
    }

    private function createSingle(): Single
    {
        return Single::query()->create([
            'title' => 'Video Player Test Single '.uniqid(),
            'slug' => 'video-player-test-single-'.uniqid(),
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
