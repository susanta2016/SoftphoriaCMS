<?php

namespace Tests\Support;

use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Support\Facades\Storage;

/**
 * Shared across tests/Feature/Commerce/* and tests/Feature/Admin/*
 * Commerce-related tests — every one of them needs a ready-to-purchase
 * Album/Single (published, with a published Track carrying a real audio
 * asset) and an admin user, so this is the "genuinely reused by 2+ things"
 * case the rest of this codebase's tests handle by duplicating a private
 * helper per file instead (see AlbumTest::createAlbum()/admin()) — Commerce
 * needs the identical fixture in ~8 files, past the point that's still the
 * better trade-off.
 */
trait CreatesCommerceFixtures
{
    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    private function audioMedia(): Media
    {
        $media = new Media;
        $media->disk = 'local';
        $media->path = 'media/audio/test-track.mp3';
        $media->original_filename = 'test-track.mp3';
        $media->mime_type = 'audio/mpeg';
        $media->size = 1024;
        $media->visibility = 'protected';
        $media->save();

        return $media;
    }

    /**
     * A published Single with one published Track carrying a real audio
     * asset — i.e. purchase-ready per CheckSingleReadinessAction.
     */
    private function readySingle(): Single
    {
        $single = Single::query()->create([
            'title' => 'Ready Single',
            'slug' => 'ready-single-'.uniqid(),
            'status' => ReleaseStatus::Published,
        ]);

        Track::query()->create([
            'single_id' => $single->getKey(),
            'title' => 'Ready Single Track',
            'slug' => 'ready-single-track-'.uniqid(),
            'status' => TrackStatus::Published,
            'audio_media_id' => $this->audioMedia()->getKey(),
        ]);

        return $single->refresh();
    }

    /**
     * A published Album with $count published Tracks, each carrying its own
     * distinct audio file actually written to the (faked) local disk — for
     * per-track quota and full-album ZIP tests that need real, tellable-
     * apart files. Call Storage::fake('local') first.
     */
    private function readyAlbumWithTracks(int $count, string $title = 'Multi Track Album'): Album
    {
        $album = Album::query()->create([
            'title' => $title,
            'slug' => 'multi-track-album-'.uniqid(),
            'status' => ReleaseStatus::Published,
        ]);

        for ($number = 1; $number <= $count; $number++) {
            $path = "media/audio/{$album->getKey()}-track-{$number}.mp3";
            Storage::disk('local')->put($path, "audio-bytes-{$album->getKey()}-{$number}");

            $media = new Media;
            $media->disk = 'local';
            $media->path = $path;
            $media->original_filename = "track-{$number}.mp3";
            $media->mime_type = 'audio/mpeg';
            $media->size = 1024;
            $media->visibility = 'protected';
            $media->save();

            Track::query()->create([
                'album_id' => $album->getKey(),
                'title' => "{$title} Song {$number}",
                'slug' => "album-{$album->getKey()}-song-{$number}-".uniqid(),
                'track_number' => $number,
                'status' => TrackStatus::Published,
                'audio_media_id' => $media->getKey(),
            ]);
        }

        return $album->refresh();
    }

    /**
     * A published Album with one published Track carrying a real audio
     * asset — i.e. purchase-ready per CheckAlbumReadinessAction.
     */
    private function readyAlbum(): Album
    {
        $album = Album::query()->create([
            'title' => 'Ready Album',
            'slug' => 'ready-album-'.uniqid(),
            'status' => ReleaseStatus::Published,
        ]);

        Track::query()->create([
            'album_id' => $album->getKey(),
            'title' => 'Ready Album Track',
            'slug' => 'ready-album-track-'.uniqid(),
            'track_number' => 1,
            'status' => TrackStatus::Published,
            'audio_media_id' => $this->audioMedia()->getKey(),
        ]);

        return $album->refresh();
    }
}
