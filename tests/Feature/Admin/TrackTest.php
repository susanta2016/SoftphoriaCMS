<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Exceptions\InvalidTrackReleaseException;
use App\Modules\Music\Filament\Resources\Tracks\Pages\CreateTrack;
use App\Modules\Music\Filament\Resources\Tracks\Pages\EditTrack;
use App\Modules\Music\Filament\Resources\Tracks\Pages\ListTracks;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One song (Database Specification §19's `tracks` table) — belongs to
 * exactly one of an Album or a Single. Covers the release-relationship
 * hardening pass: a Track must never be saveable with both album_id and
 * single_id set, or neither, at the model layer (Track::booted()), on top
 * of the Filament form/Action-layer enforcement already covered by the
 * "for an album"/"for a single" creation tests below.
 */
class TrackTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_tracks(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/tracks');

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_track_for_an_album(): void
    {
        $album = $this->createAlbum();
        $category = Category::query()->create(['type' => 'music', 'name' => 'Acoustic', 'slug' => 'acoustic']);
        $tag = Tag::query()->create(['name' => 'Presence', 'slug' => 'presence']);

        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->fillForm([
                'release' => "album:{$album->id}",
                'title' => 'Here I Am',
                'slug' => 'here-i-am-track',
                'description' => 'An invitation to presence.',
                'track_number' => 1,
                'written_by' => 'IAWARII',
                'produced_by' => 'IAWARII',
                'isrc' => 'US-AT2-24-00001',
                'status' => 'published',
                'lyrics' => ['content' => 'Here I am, in the quiet of the morning', 'visibility' => 'public'],
                'song_story' => ['content' => 'This song was born during a season of deep listening.'],
                'credits' => [['role' => 'Vocals, Lyrics, Composition', 'name' => 'IAWARII']],
                'categoryIds' => [$category->id],
                'tagIds' => [$tag->id],
                'seo' => ['meta_title' => 'Here I Am'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $track = Track::query()->where('slug', 'here-i-am-track')->firstOrFail();

        $this->assertSame($album->id, $track->album_id);
        $this->assertNull($track->single_id);
        $this->assertStringContainsString('An invitation to presence.', $track->description);
        $this->assertSame('This song was born during a season of deep listening.', $track->songStory->content);
        $this->assertNotSame($track->description, $track->songStory->content);
        $this->assertSame('Here I am, in the quiet of the morning', $track->lyrics->content);
        $this->assertCount(1, $track->credits);
        $this->assertTrue($track->categories->contains($category));
        $this->assertTrue($track->tags->contains($tag));
        $this->assertSame('Here I Am', $track->seo->meta_title);
        $this->assertTrue($track->release()->is($album));
        $this->assertSame('album', $track->releaseType());
    }

    public function test_admin_can_create_a_track_for_a_single(): void
    {
        $single = $this->createSingle();

        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->fillForm([
                'release' => "single:{$single->id}",
                'title' => 'Still Water',
                'slug' => 'still-water-track',
                'status' => 'published',
                'lyrics' => ['content' => 'lyrics here'],
                'song_story' => [],
                'credits' => [],
                'categoryIds' => [],
                'tagIds' => [],
                'seo' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $track = Track::query()->where('slug', 'still-water-track')->firstOrFail();

        $this->assertNull($track->album_id);
        $this->assertSame($single->id, $track->single_id);
        $this->assertTrue($track->release()->is($single));
        $this->assertSame('single', $track->releaseType());
    }

    public function test_creating_a_track_without_a_release_fails_validation(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->fillForm([
                'title' => 'Orphan Track',
                'slug' => 'orphan-track',
                'release' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['release']);

        $this->assertDatabaseMissing('tracks', ['slug' => 'orphan-track']);
    }

    public function test_track_cannot_be_saved_with_both_album_and_single(): void
    {
        $album = $this->createAlbum();
        $single = $this->createSingle();

        $this->expectException(InvalidTrackReleaseException::class);

        Track::query()->create([
            'album_id' => $album->id,
            'single_id' => $single->id,
            'title' => 'Invalid Track',
            'slug' => 'invalid-track-both',
        ]);
    }

    public function test_track_cannot_be_saved_with_neither_album_nor_single(): void
    {
        $this->expectException(InvalidTrackReleaseException::class);

        Track::query()->create([
            'title' => 'Invalid Track',
            'slug' => 'invalid-track-neither',
        ]);
    }

    public function test_admin_can_move_a_track_from_album_to_single(): void
    {
        $album = $this->createAlbum();
        $single = $this->createSingle();
        $track = Track::query()->create([
            'album_id' => $album->id,
            'title' => 'Movable Track',
            'slug' => 'movable-track',
            'track_number' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->fillForm(['release' => "single:{$single->id}"])
            ->call('save')
            ->assertHasNoFormErrors();

        $track->refresh();
        $this->assertNull($track->album_id);
        $this->assertSame($single->id, $track->single_id);
    }

    public function test_admin_can_move_a_track_from_single_to_album(): void
    {
        $album = $this->createAlbum();
        $single = $this->createSingle();
        $track = Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Movable Track',
            'slug' => 'movable-track-2',
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditTrack::class, ['record' => $track->getRouteKey()])
            ->fillForm(['release' => "album:{$album->id}", 'track_number' => 3])
            ->call('save')
            ->assertHasNoFormErrors();

        $track->refresh();
        $this->assertSame($album->id, $track->album_id);
        $this->assertNull($track->single_id);
        $this->assertSame(3, $track->track_number);
    }

    public function test_track_number_must_be_unique_within_its_album(): void
    {
        $album = $this->createAlbum();
        Track::query()->create([
            'album_id' => $album->id,
            'title' => 'First Track',
            'slug' => 'first-track',
            'track_number' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->fillForm([
                'release' => "album:{$album->id}",
                'title' => 'Duplicate Number Track',
                'slug' => 'duplicate-number-track',
                'track_number' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['track_number']);
    }

    public function test_track_number_can_repeat_across_different_albums(): void
    {
        $albumOne = $this->createAlbum(['slug' => 'album-one']);
        $albumTwo = Album::query()->create(['title' => 'Album Two', 'slug' => 'album-two', 'status' => ReleaseStatus::Draft]);
        Track::query()->create([
            'album_id' => $albumOne->id,
            'title' => 'Track One',
            'slug' => 'album-one-track-1',
            'track_number' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(CreateTrack::class)
            ->fillForm([
                'release' => "album:{$albumTwo->id}",
                'title' => 'Track One Again',
                'slug' => 'album-two-track-1',
                'track_number' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tracks', ['slug' => 'album-two-track-1', 'track_number' => 1]);
    }

    public function test_admin_can_view_the_track_list(): void
    {
        $album = $this->createAlbum();
        $track = Track::query()->create([
            'album_id' => $album->id,
            'title' => 'Here I Am',
            'slug' => 'here-i-am-track',
            'track_number' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->assertCanSeeTableRecords([$track]);
    }

    public function test_admin_can_delete_a_track(): void
    {
        $album = $this->createAlbum();
        $track = Track::query()->create([
            'album_id' => $album->id,
            'title' => 'Here I Am',
            'slug' => 'here-i-am-track',
            'track_number' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListTracks::class)
            ->callTableAction('delete', $track);

        $this->assertSoftDeleted('tracks', ['id' => $track->id]);
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

    private function createSingle(array $overrides = []): Single
    {
        return Single::query()->create([
            'title' => 'Still Water',
            'slug' => 'still-water',
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
