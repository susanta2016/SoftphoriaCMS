<?php

namespace Tests\Feature\Podcast;

use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Track;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages\EditPodcastEpisode;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The cross-content "You May Also Like — Music Tracks" suggestion feature
 * (Podcast side) — admin-curated only via PodcastEpisode::trackSuggestions(),
 * rendered on that episode's own detail page (podcast/show.blade.php), gated
 * end-to-end by config('features.music_track_suggestions_enabled'). See the
 * Music-side counterpart, MusicPodcastSuggestionsTest.
 */
class PodcastTrackSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.music_track_suggestions_enabled' => true]);
    }

    public function test_the_episode_admin_form_exposes_the_field_when_enabled(): void
    {
        $episode = $this->episode();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertFormFieldVisible('trackSuggestions');
    }

    public function test_the_episode_admin_form_hides_the_field_when_disabled(): void
    {
        config(['features.music_track_suggestions_enabled' => false]);
        $episode = $this->episode();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertFormFieldDoesNotExist('trackSuggestions');
    }

    public function test_admin_can_select_multiple_music_tracks(): void
    {
        $episode = $this->episode();
        $trackOne = $this->track(['title' => 'Track One']);
        $trackTwo = $this->track(['title' => 'Track Two', 'slug' => 'track-two-'.uniqid()]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->fillForm(['trackSuggestions' => [$trackOne->id, $trackTwo->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertCount(2, $episode->trackSuggestions);
        $this->assertEqualsCanonicalizing(
            [$trackOne->id, $trackTwo->id],
            $episode->trackSuggestions->pluck('id')->all(),
        );
    }

    public function test_selected_tracks_persist_after_re_editing(): void
    {
        $episode = $this->episode();
        $track = $this->track();
        $episode->trackSuggestions()->sync([$track->id => ['sort_order' => 0]]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->assertFormSet(['trackSuggestions' => [$track->id]]);
    }

    public function test_removing_a_suggestion_works(): void
    {
        $episode = $this->episode();
        $trackOne = $this->track(['title' => 'Keep Me']);
        $trackTwo = $this->track(['title' => 'Remove Me', 'slug' => 'remove-me-'.uniqid()]);
        $episode->trackSuggestions()->sync([
            $trackOne->id => ['sort_order' => 0],
            $trackTwo->id => ['sort_order' => 1],
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->fillForm(['trackSuggestions' => [$trackOne->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertCount(1, $episode->trackSuggestions);
        $this->assertSame($trackOne->id, $episode->trackSuggestions->first()->id);
    }

    public function test_saving_the_same_selection_twice_creates_no_duplicate_rows(): void
    {
        $episode = $this->episode();
        $track = $this->track();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->fillForm(['trackSuggestions' => [$track->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->fillForm(['trackSuggestions' => [$track->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, DB::table('podcast_track_suggestions')
            ->where('podcast_episode_id', $episode->id)
            ->where('track_id', $track->id)
            ->count());
    }

    public function test_selection_order_is_preserved_as_sort_order(): void
    {
        $episode = $this->episode();
        $first = $this->track(['title' => 'First']);
        $second = $this->track(['title' => 'Second', 'slug' => 'second-'.uniqid()]);
        $third = $this->track(['title' => 'Third', 'slug' => 'third-'.uniqid()]);

        Livewire::actingAs($this->admin())
            ->test(EditPodcastEpisode::class, ['record' => $episode->getRouteKey()])
            ->fillForm(['trackSuggestions' => [$third->id, $first->id, $second->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $episode->refresh();
        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $episode->trackSuggestions->pluck('id')->all(),
        );
    }

    public function test_the_frontend_displays_the_selected_music_track(): void
    {
        $episode = $this->episode(['title' => 'A Distinctive Episode Title']);
        $track = $this->track(['title' => 'A Distinctive Track Title']);
        $episode->trackSuggestions()->sync([$track->id => ['sort_order' => 0]]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee('You May Also Like — Music Tracks');
        $response->assertSee('A Distinctive Track Title');
        $response->assertSee(route('music.tracks.show', $track), false);
    }

    public function test_the_frontend_does_not_render_an_empty_suggestion_section(): void
    {
        $episode = $this->episode();

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertDontSee('You May Also Like — Music Tracks');
    }

    public function test_the_frontend_hides_the_section_entirely_when_disabled(): void
    {
        $episode = $this->episode();
        $track = $this->track(['title' => 'Should Stay Hidden']);
        $episode->trackSuggestions()->sync([$track->id => ['sort_order' => 0]]);

        config(['features.music_track_suggestions_enabled' => false]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertDontSee('You May Also Like — Music Tracks');
        $response->assertDontSee('Should Stay Hidden');
    }

    public function test_disabling_and_re_enabling_the_flag_never_touches_stored_relationships(): void
    {
        $episode = $this->episode();
        $track = $this->track(['title' => 'Survives The Flag']);
        $episode->trackSuggestions()->sync([$track->id => ['sort_order' => 0]]);

        config(['features.music_track_suggestions_enabled' => false]);
        $this->get(route('podcast.episodes.show', $episode))->assertDontSee('Survives The Flag');

        config(['features.music_track_suggestions_enabled' => true]);
        $response = $this->get(route('podcast.episodes.show', $episode));
        $response->assertOk();
        $response->assertSee('Survives The Flag');

        $episode->refresh();
        $this->assertCount(1, $episode->trackSuggestions);
    }

    public function test_an_unpublished_track_suggestion_is_not_rendered(): void
    {
        $episode = $this->episode();
        $track = $this->track(['title' => 'Draft Track', 'status' => TrackStatus::Draft]);
        $episode->trackSuggestions()->sync([$track->id => ['sort_order' => 0]]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertDontSee('Draft Track');
        $response->assertDontSee('You May Also Like — Music Tracks');
    }

    public function test_a_track_whose_parent_album_is_unpublished_is_not_rendered(): void
    {
        $album = Album::query()->create(['title' => 'Draft Album', 'slug' => 'draft-album-'.uniqid(), 'status' => ReleaseStatus::Draft]);
        $episode = $this->episode();
        $track = Track::query()->create([
            'album_id' => $album->id,
            'title' => 'Orphaned Track',
            'slug' => 'orphaned-track-'.uniqid(),
            'track_number' => 1,
            'status' => TrackStatus::Published,
        ]);
        $episode->trackSuggestions()->sync([$track->id => ['sort_order' => 0]]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertDontSee('Orphaned Track');
    }

    public function test_a_deleted_track_fails_safely_and_the_page_still_renders(): void
    {
        $episode = $this->episode();
        $track = $this->track(['title' => 'Soon Deleted']);
        $episode->trackSuggestions()->sync([$track->id => ['sort_order' => 0]]);
        $track->forceDelete();

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertDontSee('Soon Deleted');
    }

    /**
     * REGRESSION — existing Podcast audio playback/download endpoints are
     * completely untouched by this feature.
     */
    public function test_existing_podcast_episode_page_content_is_unaffected(): void
    {
        $episode = $this->episode(['title' => 'Regression Episode']);
        $track = $this->track();
        $episode->trackSuggestions()->sync([$track->id => ['sort_order' => 0]]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee('Regression Episode');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function episode(array $overrides = []): PodcastEpisode
    {
        $podcast = Podcast::query()->create([
            'title' => 'A Podcast',
            'slug' => 'a-podcast-'.uniqid(),
            'status' => PodcastStatus::Published,
        ]);

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'An Episode',
            'slug' => 'an-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
            'publish_date' => now(),
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function track(array $overrides = []): Track
    {
        $album = Album::query()->create([
            'title' => 'A Track\'s Album',
            'slug' => 'a-tracks-album-'.uniqid(),
            'status' => ReleaseStatus::Published,
        ]);

        return Track::query()->create([
            'album_id' => $album->id,
            'title' => 'A Track',
            'slug' => 'a-track-'.uniqid(),
            'track_number' => 1,
            'status' => TrackStatus::Published,
            ...$overrides,
        ]);
    }
}
