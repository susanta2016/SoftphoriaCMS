<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Filament\Resources\Tracks\Pages\CreateTrack;
use App\Modules\Music\Filament\Resources\Tracks\Pages\EditTrack;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Modules\Music\Support\AudioDurationDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The fix for the 2026-08-31 guest-limit bypass bug: a Track saved with
 * audio but no admin-entered duration previously left duration_seconds
 * null, which made TrackStreamController's guest byte-cap silently fall
 * back to serving the entire file. duration_seconds is no longer an
 * admin-editable field at all (see TrackForm's read-only "Length"
 * Placeholder, decided 2026-08-31 once a *manually-typed* under-estimate
 * was recognized as the same bypass class reachable a different way) — it
 * is always recomputed from the real uploaded file (via getID3,
 * AudioDurationDetector) on every Create/UpdateTrackAction save, and
 * cleared when the audio is removed. See TrackStreamControllerTest for the
 * companion proof that the endpoint itself also fails closed if a duration
 * is somehow still unknown.
 */
class TrackAudioDurationDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_track_with_audio_and_a_blank_duration_auto_fills_it_from_the_real_file(): void
    {
        Storage::fake('local');
        $single = $this->createSingle();

        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->fillForm([
                'release' => "single:{$single->id}",
                'title' => 'Auto Duration Track',
                'slug' => 'auto-duration-track',
                'status' => 'draft',
            ])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_upload', data: [
                'file' => UploadedFile::fake()->createWithContent('song.wav', $this->makeWavBytes(7)),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('create')
            ->assertHasNoFormErrors();

        $track = Track::query()->where('slug', 'auto-duration-track')->firstOrFail();

        $this->assertSame(7, $track->duration_seconds);
    }

    /**
     * duration_seconds is not an admin-editable form field at all (see
     * TrackForm's read-only "Length" Placeholder) — a stray/stale
     * 'duration_seconds' key in a submission (e.g. a replayed or
     * hand-crafted request) has nothing to bind to and is silently
     * ignored; the stored value always comes from re-detecting the real
     * file. This closes the under-estimate bypass: a manually-typed
     * duration lower than the file's real length would otherwise let a
     * guest's byte-cap fraction exceed 1 and clamp to the full file.
     */
    public function test_a_submitted_duration_seconds_value_is_ignored_in_favor_of_the_real_detected_one(): void
    {
        Storage::fake('local');
        $single = $this->createSingle();

        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->fillForm([
                'release' => "single:{$single->id}",
                'title' => 'Ignored Duration Track',
                'slug' => 'ignored-duration-track',
                'status' => 'draft',
                'duration_seconds' => 999,
            ])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_upload', data: [
                'file' => UploadedFile::fake()->createWithContent('song.wav', $this->makeWavBytes(7)),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('create')
            ->assertHasNoFormErrors();

        $track = Track::query()->where('slug', 'ignored-duration-track')->firstOrFail();

        $this->assertSame(7, $track->duration_seconds);
    }

    public function test_replacing_the_audio_file_recomputes_duration_even_when_a_value_was_already_stored(): void
    {
        Storage::fake('local');
        $single = $this->createSingle();
        $track = Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Replace Audio Track',
            'slug' => 'replace-audio-track',
            'duration_seconds' => 500,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_upload', data: [
                'file' => UploadedFile::fake()->createWithContent('song.wav', $this->makeWavBytes(20)),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $track->refresh();
        $this->assertSame(20, $track->duration_seconds);
    }

    public function test_removing_the_audio_file_clears_the_stored_duration(): void
    {
        Storage::fake('local');
        $single = $this->createSingle();
        $media = new Media;
        $media->disk = 'local';
        $media->path = 'media/audio/to-be-removed.wav';
        Storage::disk('local')->put($media->path, $this->makeWavBytes(15));
        $media->original_filename = 'to-be-removed.wav';
        $media->mime_type = 'audio/wav';
        $media->size = strlen($this->makeWavBytes(15));
        $media->visibility = 'protected';
        $media->save();

        $track = Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Clear Duration Track',
            'slug' => 'clear-duration-track',
            'audio_media_id' => $media->id,
            'duration_seconds' => 15,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_clear')
            ->call('save')
            ->assertHasNoFormErrors();

        $track->refresh();
        $this->assertNull($track->audio_media_id);
        $this->assertNull($track->duration_seconds);
    }

    public function test_editing_a_track_with_no_audio_leaves_duration_null_without_error(): void
    {
        $single = $this->createSingle();

        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->fillForm([
                'release' => "single:{$single->id}",
                'title' => 'No Audio Track',
                'slug' => 'no-audio-track',
                'status' => 'draft',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $track = Track::query()->where('slug', 'no-audio-track')->firstOrFail();

        $this->assertNull($track->duration_seconds);
    }

    public function test_updating_an_existing_track_with_audio_and_blank_duration_also_auto_fills_it(): void
    {
        Storage::fake('local');
        $single = $this->createSingle();
        $track = Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Update Auto Duration Track',
            'slug' => 'update-auto-duration-track',
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->callFormComponentAction('audio_media_id__actions', 'audio_media_id_upload', data: [
                'file' => UploadedFile::fake()->createWithContent('song.wav', $this->makeWavBytes(12)),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $track->refresh();
        $this->assertSame(12, $track->duration_seconds);
    }

    public function test_the_detector_returns_null_for_unparseable_content_rather_than_guessing(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/audio/garbage.mp3', 'not-a-real-audio-file');

        $media = new Media;
        $media->disk = 'local';
        $media->path = 'media/audio/garbage.mp3';
        $media->original_filename = 'garbage.mp3';
        $media->mime_type = 'audio/mpeg';
        $media->size = 22;
        $media->visibility = 'protected';
        $media->save();

        $seconds = app(AudioDurationDetector::class)->detect($media);

        $this->assertNull($seconds);
    }

    public function test_the_detector_returns_the_exact_duration_for_a_real_wav_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/audio/exact.wav', $this->makeWavBytes(9));

        $media = new Media;
        $media->disk = 'local';
        $media->path = 'media/audio/exact.wav';
        $media->original_filename = 'exact.wav';
        $media->mime_type = 'audio/wav';
        $media->size = strlen($this->makeWavBytes(9));
        $media->visibility = 'protected';
        $media->save();

        $seconds = app(AudioDurationDetector::class)->detect($media);

        $this->assertSame(9, $seconds);
    }

    /**
     * A minimal, fully valid mono 8-bit PCM WAV file of exactly
     * $durationSeconds — silent (zero) sample data, but a real, parseable
     * RIFF/fmt/data structure any correct WAV reader (getID3 included)
     * computes an exact duration from.
     */
    private function makeWavBytes(int $durationSeconds, int $sampleRate = 8000): string
    {
        $numChannels = 1;
        $bitsPerSample = 8;
        $byteRate = (int) ($sampleRate * $numChannels * $bitsPerSample / 8);
        $blockAlign = (int) ($numChannels * $bitsPerSample / 8);
        $dataSize = $byteRate * $durationSeconds;

        $header = 'RIFF'.pack('V', 36 + $dataSize).'WAVE';
        $header .= 'fmt '.pack('V', 16).pack('v', 1).pack('v', $numChannels).pack('V', $sampleRate).pack('V', $byteRate).pack('v', $blockAlign).pack('v', $bitsPerSample);
        $header .= 'data'.pack('V', $dataSize);

        return $header.str_repeat("\x80", $dataSize);
    }

    private function createSingle(): Single
    {
        return Single::query()->create([
            'title' => 'Duration Detection Single '.uniqid(),
            'slug' => 'duration-detection-single-'.uniqid(),
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
