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
