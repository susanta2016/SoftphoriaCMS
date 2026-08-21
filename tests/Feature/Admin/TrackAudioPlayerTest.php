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
 * Admin-only audio player/preview for Tracks. Deliberately reuses the
 * existing private-media streaming route (`media.stream` /
 * StreamMediaController) and MediaPicker's own preview mechanism — see
 * App\Filament\Support\Media\MediaPreview — rather than a second streaming
 * or storage path. Playback-only: never touches Commerce (no entitlement,
 * no download count, no download log).
 */
class TrackAudioPlayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_track_list_shows_an_audio_player_for_a_track_with_audio(): void
    {
        $track = $this->createTrackWithAudio();

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->assertSee(route('media.stream', $track->audio), false);
    }

    public function test_the_track_list_shows_a_no_audio_state_for_a_track_without_audio(): void
    {
        $this->createTrackWithoutAudio();

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->assertSee('No audio')
            ->assertDontSee('<audio', false);
    }

    public function test_the_track_edit_page_shows_the_audio_player_when_audio_is_set(): void
    {
        $track = $this->createTrackWithAudio();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->assertSee(route('media.stream', $track->audio), false);
    }

    public function test_the_track_edit_page_shows_a_no_audio_state_when_audio_is_not_set(): void
    {
        $track = $this->createTrackWithoutAudio();

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->assertSee('No Audio selected.');
    }

    public function test_an_authorized_admin_can_stream_the_previewed_audio(): void
    {
        $track = $this->createTrackWithAudio();

        $response = $this->actingAs($this->admin())->get(route('media.stream', $track->audio));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/mpeg');
    }

    public function test_an_unauthenticated_guest_cannot_stream_the_previewed_audio(): void
    {
        $track = $this->createTrackWithAudio();

        $response = $this->get(route('media.stream', $track->audio));

        $response->assertForbidden();
    }

    public function test_a_non_admin_cannot_stream_the_previewed_audio(): void
    {
        $track = $this->createTrackWithAudio();
        $nonAdmin = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($nonAdmin)->get(route('media.stream', $track->audio));

        $response->assertForbidden();
    }

    /**
     * The whole point of reusing StreamMediaController rather than a
     * download-authorization endpoint: playback must never touch Commerce
     * state, regardless of how many times an admin plays the file back.
     */
    public function test_previewing_audio_does_not_create_or_change_any_commerce_entitlement_or_download_count(): void
    {
        $track = $this->createTrackWithAudio();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('media.stream', $track->audio))->assertOk();
        $this->actingAs($admin)->get(route('media.stream', $track->audio))->assertOk();
        $this->actingAs($admin)->get(route('media.stream', $track->audio))->assertOk();

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
        $track = $this->createTrackWithAudio();

        $html = (string) MediaPreview::audioPlayer($track->audio);

        $this->assertStringContainsString(route('media.stream', $track->audio), $html);
        $this->assertStringNotContainsString($track->audio->path, $html);
        $this->assertStringNotContainsString('/storage/', $html);
    }

    private function createTrackWithAudio(): Track
    {
        Storage::fake('local');

        $single = $this->createSingle();

        $path = 'media/audio/player-test-'.uniqid().'.mp3';
        Storage::disk('local')->put($path, 'fake-mp3-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = 'player-test.mp3';
        $media->mime_type = 'audio/mpeg';
        $media->size = 14;
        $media->visibility = 'protected';
        $media->save();

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Player Test Track',
            'slug' => 'player-test-track-'.uniqid(),
            'audio_media_id' => $media->id,
        ]);
    }

    private function createTrackWithoutAudio(): Track
    {
        $single = $this->createSingle();

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'No Audio Track',
            'slug' => 'no-audio-track-'.uniqid(),
        ]);
    }

    private function createSingle(): Single
    {
        return Single::query()->create([
            'title' => 'Player Test Single '.uniqid(),
            'slug' => 'player-test-single-'.uniqid(),
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
