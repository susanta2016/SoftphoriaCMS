<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Filament\Resources\Albums\Pages\CreateAlbum;
use App\Modules\Music\Filament\Resources\Albums\Pages\EditAlbum;
use App\Modules\Music\Filament\Resources\Albums\Pages\ListAlbums;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A multi-track release (Database Specification §19's `albums` table).
 * See TrackTest for track CRUD and the Album/Single release relationship
 * hardening tests, and AlbumTracksRelationManagerTest for reordering.
 */
class AlbumTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_albums(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/albums');

        $response->assertForbidden();
    }

    public function test_admin_can_create_an_album_with_links_seo_and_video(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAlbum::class)
            ->fillForm([
                'title' => 'Here I Am',
                'slug' => 'here-i-am',
                'description' => 'A song is a message about meaning.',
                'embed_video_url' => 'https://www.youtube.com/watch?v=abc123',
                'status' => ReleaseStatus::Published->value,
                'is_featured' => true,
                'links' => [
                    ['provider' => 'spotify', 'url' => 'https://open.spotify.com/album/xyz'],
                ],
                'seo' => ['meta_title' => 'Here I Am'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $album = Album::query()->where('slug', 'here-i-am')->firstOrFail();

        $this->assertSame('https://www.youtube.com/watch?v=abc123', $album->embed_video_url);
        $this->assertTrue($album->is_featured);
        $this->assertCount(1, $album->streamingLinks);
        $this->assertSame('https://open.spotify.com/album/xyz', $album->streamingLinks->first()->url);
        $this->assertSame('Here I Am', $album->seo->meta_title);
    }

    public function test_admin_can_view_the_album_list(): void
    {
        $album = $this->createAlbum();

        Livewire::actingAs($this->admin())
            ->test(ListAlbums::class)
            ->assertCanSeeTableRecords([$album]);
    }

    public function test_admin_can_update_an_album(): void
    {
        $album = $this->createAlbum();

        Livewire::actingAs($this->admin())
            ->test(EditAlbum::class, ['record' => $album->getRouteKey()])
            ->fillForm(['title' => 'Renamed Album', 'embed_video_url' => 'https://vimeo.com/12345'])
            ->call('save')
            ->assertHasNoFormErrors();

        $album->refresh();
        $this->assertSame('Renamed Album', $album->title);
        $this->assertSame('https://vimeo.com/12345', $album->embed_video_url);
    }

    public function test_scheduling_an_album_requires_a_publish_at_time(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAlbum::class)
            ->fillForm([
                'title' => 'Scheduled Album',
                'slug' => 'scheduled-album',
                'status' => ReleaseStatus::Scheduled->value,
                'publish_at' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['publish_at']);
    }

    public function test_deleting_an_album_with_tracks_is_blocked(): void
    {
        $album = $this->createAlbum();
        Track::query()->create([
            'album_id' => $album->id,
            'title' => 'Track One',
            'slug' => 'track-one',
            'track_number' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListAlbums::class)
            ->callTableAction('deleteAlbum', $album)
            ->assertNotified();

        $this->assertDatabaseHas('albums', ['id' => $album->id, 'deleted_at' => null]);
    }

    public function test_deleting_an_album_with_no_tracks_succeeds(): void
    {
        $album = $this->createAlbum();

        Livewire::actingAs($this->admin())
            ->test(ListAlbums::class)
            ->callTableAction('deleteAlbum', $album);

        $this->assertSoftDeleted('albums', ['id' => $album->id]);
    }

    /**
     * Requirement 7 of the hardening pass: no artificial "only one featured
     * release" constraint should exist unless explicitly required.
     */
    public function test_multiple_albums_can_be_featured_simultaneously(): void
    {
        $first = $this->createAlbum(['is_featured' => true]);
        $second = Album::query()->create([
            'title' => 'Second Album',
            'slug' => 'second-album',
            'status' => ReleaseStatus::Published,
            'is_featured' => true,
        ]);

        $this->assertTrue($first->refresh()->is_featured);
        $this->assertTrue($second->refresh()->is_featured);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAlbum(array $overrides = []): Album
    {
        return Album::query()->create([
            'title' => 'Here I Am',
            'slug' => 'here-i-am',
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
