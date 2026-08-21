<?php

namespace Tests\Feature\Admin;

use App\Modules\Commerce\Actions\Order\CreatePendingOrderAction;
use App\Modules\Music\Actions\Album\DeleteAlbumAction;
use App\Modules\Music\Actions\Single\DeleteSingleAction;
use App\Modules\Music\Exceptions\AlbumInUseException;
use App\Modules\Music\Exceptions\SingleInUseException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCommerceFixtures;
use Tests\TestCase;

/**
 * ADMIN-008: a purchased Album/Single must never become deletable — see
 * DeleteAlbumAction/DeleteSingleAction's new guards.
 */
class AlbumSingleDeletionGuardTest extends TestCase
{
    use CreatesCommerceFixtures, RefreshDatabase;

    public function test_a_purchased_album_cannot_be_deleted_even_once_its_tracks_are_gone(): void
    {
        $admin = $this->admin();
        $album = $this->readyAlbum();
        app(CreatePendingOrderAction::class)->handle($album, null, 'guest@example.com');

        // Isolates the new purchased-guard from the pre-existing
        // "still has tracks" guard, which would otherwise also block this.
        $album->tracks()->delete();

        $this->expectException(AlbumInUseException::class);
        $this->expectExceptionMessage('has been purchased');

        app(DeleteAlbumAction::class)->handle($album->refresh(), $admin);
    }

    public function test_a_purchased_single_cannot_be_deleted_even_once_its_track_is_gone(): void
    {
        $admin = $this->admin();
        $single = $this->readySingle();
        app(CreatePendingOrderAction::class)->handle($single, null, 'guest@example.com');

        $single->track()->delete();

        $this->expectException(SingleInUseException::class);
        $this->expectExceptionMessage('has been purchased');

        app(DeleteSingleAction::class)->handle($single->refresh(), $admin);
    }
}
