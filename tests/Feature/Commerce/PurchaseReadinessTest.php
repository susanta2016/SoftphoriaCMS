<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Actions\PurchaseReadiness\CheckAlbumReadinessAction;
use App\Modules\Commerce\Actions\PurchaseReadiness\CheckSingleReadinessAction;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * §15/§9/§22 of the approved brief — the single reusable readiness check
 * every purchase path relies on.
 */
class PurchaseReadinessTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_ready_single_passes(): void
    {
        $result = app(CheckSingleReadinessAction::class)->handle($this->readySingle());

        $this->assertTrue($result->ready);
        $this->assertSame([], $result->issues);
    }

    public function test_unpublished_single_cannot_be_purchased(): void
    {
        $single = $this->readySingle();
        $single->update(['status' => ReleaseStatus::Draft]);

        $result = app(CheckSingleReadinessAction::class)->handle($single->refresh());

        $this->assertFalse($result->ready);
    }

    public function test_single_with_no_track_cannot_be_purchased(): void
    {
        $single = Single::query()->create([
            'title' => 'No Track Single',
            'slug' => 'no-track-single',
            'status' => ReleaseStatus::Published,
        ]);

        $result = app(CheckSingleReadinessAction::class)->handle($single);

        $this->assertFalse($result->ready);
        $this->assertSame(['Single has no Track.'], $result->issues);
    }

    public function test_single_track_missing_audio_is_detected(): void
    {
        $single = $this->readySingle();
        $single->track->update(['audio_media_id' => null]);

        $result = app(CheckSingleReadinessAction::class)->handle($single->refresh());

        $this->assertFalse($result->ready);
        $this->assertContains('Track has no audio file.', $result->issues);
    }

    public function test_ready_album_passes(): void
    {
        $result = app(CheckAlbumReadinessAction::class)->handle($this->readyAlbum());

        $this->assertTrue($result->ready);
    }

    public function test_unpublished_album_cannot_be_purchased(): void
    {
        $album = $this->readyAlbum();
        $album->update(['status' => ReleaseStatus::Draft]);

        $result = app(CheckAlbumReadinessAction::class)->handle($album->refresh());

        $this->assertFalse($result->ready);
    }

    public function test_album_with_no_tracks_cannot_be_purchased(): void
    {
        $album = Album::query()->create([
            'title' => 'No Tracks Album',
            'slug' => 'no-tracks-album',
            'status' => ReleaseStatus::Published,
        ]);

        $result = app(CheckAlbumReadinessAction::class)->handle($album);

        $this->assertFalse($result->ready);
        $this->assertSame(['Album has no Tracks.'], $result->issues);
    }

    public function test_one_bad_track_fails_the_whole_album(): void
    {
        $album = $this->readyAlbum();

        Track::query()->create([
            'album_id' => $album->getKey(),
            'title' => 'Unpublished Track',
            'slug' => 'unpublished-track',
            'track_number' => 2,
            'status' => TrackStatus::Draft,
        ]);

        $result = app(CheckAlbumReadinessAction::class)->handle($album->refresh());

        $this->assertFalse($result->ready);
    }
}
