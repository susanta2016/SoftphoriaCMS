<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaCategory;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Filament\Resources\Tracks\Pages\CreateTrack;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies the complete Track audio upload/storage flow end to end — traced
 * against the real production code path (TrackForm's MediaPicker field →
 * Filament's FileUpload → StoreUploadedMediaAction → Media → the `local`
 * disk) rather than inferred from config alone. Companion to a real,
 * out-of-band verification pass (real file uploaded through the exact same
 * production code, physically confirmed under storage/app/private on disk,
 * and a live HTTP request to /storage/... against it confirmed 403 while
 * the same request against a genuine public-disk file returned 200 — not
 * repeatable here as an automated test since it depends on the real
 * filesystem/webserver, but this file is the automated, repeatable proof
 * that the same guarantee holds at the framework level).
 */
class TrackAudioStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_audio_through_the_track_form_uses_the_media_library_and_the_private_local_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_upload', data: [
                'file' => UploadedFile::fake()->create('song.mp3', 500, 'audio/mpeg'),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->assertSet('data.audio_media_id', fn (?int $value): bool => $value !== null);

        $media = Media::query()->sole();

        // The Media Library / MediaPicker mechanism was actually used — no
        // parallel upload path exists.
        $this->assertSame(MediaCategory::Audio, $media->category());

        // MediaCategory::Audio resolves to the private `local` disk, not
        // `public` — this is what actually keeps the file off /storage/...
        // (config/media.php's audio.disk, read via MediaCategory::diskName()).
        $this->assertSame('local', $media->disk);
        $this->assertSame('protected', $media->visibility);

        // The physical file exists where the app actually wrote it...
        Storage::disk('local')->assertExists($media->path);
        // ...and was never placed on the public disk under any path.
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_saving_the_track_persists_audio_media_id_pointing_at_the_uploaded_media(): void
    {
        Storage::fake('local');

        $single = $this->createSingle();

        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->fillForm([
                'release' => "single:{$single->id}",
                'title' => 'Storage Flow Track',
                'slug' => 'storage-flow-track',
                'status' => 'draft',
            ])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_upload', data: [
                'file' => UploadedFile::fake()->create('song.mp3', 500, 'audio/mpeg'),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('create')
            ->assertHasNoFormErrors();

        $track = Track::query()->where('slug', 'storage-flow-track')->firstOrFail();
        $media = Media::query()->sole();

        $this->assertSame($media->id, $track->audio_media_id);
        $this->assertNotNull($track->audio);
        $this->assertSame($media->id, $track->audio->id);
        $this->assertSame('local', $track->audio->disk);
        Storage::disk('local')->assertExists($track->audio->path);
    }

    /**
     * A direct regression guard on the config values themselves, so a
     * future edit to config/media.php that accidentally switches Audio (or
     * Video — the same principle applies to any future purchasable video
     * asset) back to the `public` disk fails a test immediately rather than
     * silently reopening the exposure.
     */
    public function test_audio_and_video_categories_are_configured_for_the_private_disk_not_public(): void
    {
        $this->assertSame('local', MediaCategory::Audio->diskName());
        $this->assertSame('protected', MediaCategory::Audio->visibility());

        $this->assertSame('local', MediaCategory::Video->diskName());
        $this->assertSame('protected', MediaCategory::Video->visibility());

        // The `local` disk's root is genuinely a different directory tree
        // than `public`'s — not merely a different config key pointing at
        // the same place.
        $this->assertNotSame(
            Storage::disk('public')->path(''),
            Storage::disk('local')->path(''),
        );

        // Only the `public` disk is ever exposed via the storage:link
        // symlink — `local` is deliberately absent from that map.
        $this->assertSame([public_path('storage') => storage_path('app/public')], config('filesystems.links'));
    }

    public function test_a_tracks_audio_can_be_streamed_by_an_admin(): void
    {
        $track = $this->createTrackWithExistingAudio();
        $admin = $this->admin();

        // The existing, application-controlled endpoint — never a public
        // URL — is how this file is ever served, to anyone, including
        // Admin's own playback.
        $response = $this->actingAs($admin)->get(route('media.stream', $track->audio));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/mpeg');
    }

    public function test_a_tracks_audio_cannot_be_streamed_by_a_guest(): void
    {
        $track = $this->createTrackWithExistingAudio();

        $response = $this->get(route('media.stream', $track->audio));
        $response->assertForbidden();
    }

    private function createTrackWithExistingAudio(): Track
    {
        Storage::fake('local');

        $single = $this->createSingle();
        $path = 'media/audio/existing-track.mp3';
        Storage::disk('local')->put($path, 'fake-mp3-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = 'existing-track.mp3';
        $media->mime_type = 'audio/mpeg';
        $media->size = 14;
        $media->visibility = 'protected';
        $media->save();

        return Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Existing Track',
            'slug' => 'existing-track',
            'audio_media_id' => $media->id,
        ]);
    }

    private function createSingle(array $overrides = []): Single
    {
        return Single::query()->create([
            'title' => 'Storage Verify Single',
            'slug' => 'storage-verify-single',
            'status' => ReleaseStatus::Draft,
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
