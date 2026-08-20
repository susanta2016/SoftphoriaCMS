<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Filament\Resources\Albums\Pages\EditAlbum;
use App\Modules\Music\Filament\Resources\Albums\RelationManagers\TracksRelationManager;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dedicated Album -> Tracks reorder experience (hardening item 4) —
 * drag-reordering updates tracks.track_number via Filament's built-in
 * reorderable table, scoped to the owning album.
 */
class AlbumTracksRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_an_albums_tracks_in_order(): void
    {
        $album = $this->createAlbum();
        $trackOne = $this->createTrack($album, 'Track One', 1);
        $trackTwo = $this->createTrack($album, 'Track Two', 2);

        Livewire::actingAs($this->admin())
            ->test(TracksRelationManager::class, ['ownerRecord' => $album, 'pageClass' => EditAlbum::class])
            ->assertCanSeeTableRecords([$trackOne, $trackTwo]);
    }

    public function test_admin_can_reorder_an_albums_tracks(): void
    {
        $album = $this->createAlbum();
        $trackOne = $this->createTrack($album, 'Track One', 1);
        $trackTwo = $this->createTrack($album, 'Track Two', 2);
        $trackThree = $this->createTrack($album, 'Track Three', 3);

        Livewire::actingAs($this->admin())
            ->test(TracksRelationManager::class, ['ownerRecord' => $album, 'pageClass' => EditAlbum::class])
            ->call('reorderTable', [$trackThree->getKey(), $trackOne->getKey(), $trackTwo->getKey()]);

        $this->assertSame(1, $trackThree->refresh()->track_number);
        $this->assertSame(2, $trackOne->refresh()->track_number);
        $this->assertSame(3, $trackTwo->refresh()->track_number);
    }

    public function test_reordering_one_albums_tracks_does_not_affect_another_albums_tracks(): void
    {
        $albumOne = $this->createAlbum(['slug' => 'album-one']);
        $albumTwo = Album::query()->create(['title' => 'Album Two', 'slug' => 'album-two', 'status' => ReleaseStatus::Draft]);
        $albumOneTrack = $this->createTrack($albumOne, 'Album One Track', 1);
        $albumTwoTrack = $this->createTrack($albumTwo, 'Album Two Track', 1);

        Livewire::actingAs($this->admin())
            ->test(TracksRelationManager::class, ['ownerRecord' => $albumOne, 'pageClass' => EditAlbum::class])
            ->call('reorderTable', [$albumOneTrack->getKey()]);

        $this->assertSame(1, $albumOneTrack->refresh()->track_number);
        $this->assertSame(1, $albumTwoTrack->refresh()->track_number);
    }

    private function createAlbum(array $overrides = []): Album
    {
        return Album::query()->create([
            'title' => 'Here I Am',
            'slug' => 'here-i-am',
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }

    private function createTrack(Album $album, string $title, int $trackNumber): Track
    {
        return Track::query()->create([
            'album_id' => $album->id,
            'title' => $title,
            'slug' => (string) str($title)->slug()->append('-'.$album->id),
            'track_number' => $trackNumber,
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
