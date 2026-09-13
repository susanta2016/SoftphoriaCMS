<?php

namespace Tests\Feature\Music;

use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Filament\Resources\Albums\Pages\EditAlbum;
use App\Modules\Music\Filament\Resources\Singles\Pages\EditSingle;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The cross-content "You May Also Like — Podcast Episodes" suggestion
 * feature (Music side) — admin-curated only via Album/Single::
 * podcastSuggestions(), rendered on that release's own detail page
 * (music/listening.blade.php), gated end-to-end by config('features.
 * podcast_suggestions_enabled'). See MusicPodcastSuggestionsTest's Podcast-
 * side counterpart, PodcastTrackSuggestionsTest.
 */
class MusicPodcastSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Defaults to false site-wide (config/features.php) — tests opt in
        // explicitly rather than depending on the developer's local .env.
        config(['features.podcast_suggestions_enabled' => true]);
    }

    public function test_the_album_admin_form_exposes_the_field_when_enabled(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);

        Livewire::actingAs($this->admin())
            ->test(EditAlbum::class, ['record' => $album->getRouteKey()])
            ->assertFormFieldVisible('podcastSuggestions');
    }

    public function test_the_album_admin_form_hides_the_field_when_disabled(): void
    {
        config(['features.podcast_suggestions_enabled' => false]);
        $album = $this->album(['status' => ReleaseStatus::Published]);

        Livewire::actingAs($this->admin())
            ->test(EditAlbum::class, ['record' => $album->getRouteKey()])
            ->assertFormFieldDoesNotExist('podcastSuggestions');
    }

    public function test_the_single_admin_form_exposes_the_field_when_enabled(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);

        Livewire::actingAs($this->admin())
            ->test(EditSingle::class, ['record' => $single->getRouteKey()])
            ->assertFormFieldVisible('podcastSuggestions');
    }

    public function test_the_single_admin_form_hides_the_field_when_disabled(): void
    {
        config(['features.podcast_suggestions_enabled' => false]);
        $single = $this->single(['status' => ReleaseStatus::Published]);

        Livewire::actingAs($this->admin())
            ->test(EditSingle::class, ['record' => $single->getRouteKey()])
            ->assertFormFieldDoesNotExist('podcastSuggestions');
    }

    public function test_admin_can_select_multiple_podcast_episodes_for_an_album(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $episodeOne = $this->episode(['title' => 'Episode One']);
        $episodeTwo = $this->episode(['title' => 'Episode Two', 'slug' => 'episode-two']);

        Livewire::actingAs($this->admin())
            ->test(EditAlbum::class, ['record' => $album->getRouteKey()])
            ->fillForm(['podcastSuggestions' => [$episodeOne->id, $episodeTwo->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $album->refresh();
        $this->assertCount(2, $album->podcastSuggestions);
        $this->assertEqualsCanonicalizing(
            [$episodeOne->id, $episodeTwo->id],
            $album->podcastSuggestions->pluck('id')->all(),
        );
    }

    public function test_selected_episodes_persist_after_re_editing(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $episode = $this->episode();
        $album->podcastSuggestions()->sync([$episode->id => ['sort_order' => 0]]);

        Livewire::actingAs($this->admin())
            ->test(EditAlbum::class, ['record' => $album->getRouteKey()])
            ->assertFormSet(['podcastSuggestions' => [$episode->id]]);
    }

    public function test_removing_a_suggestion_works(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $episodeOne = $this->episode(['title' => 'Keep Me']);
        $episodeTwo = $this->episode(['title' => 'Remove Me', 'slug' => 'remove-me']);
        $album->podcastSuggestions()->sync([
            $episodeOne->id => ['sort_order' => 0],
            $episodeTwo->id => ['sort_order' => 1],
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditAlbum::class, ['record' => $album->getRouteKey()])
            ->fillForm(['podcastSuggestions' => [$episodeOne->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $album->refresh();
        $this->assertCount(1, $album->podcastSuggestions);
        $this->assertSame($episodeOne->id, $album->podcastSuggestions->first()->id);
    }

    public function test_saving_the_same_selection_twice_creates_no_duplicate_rows(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $episode = $this->episode();

        Livewire::actingAs($this->admin())
            ->test(EditAlbum::class, ['record' => $album->getRouteKey()])
            ->fillForm(['podcastSuggestions' => [$episode->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::actingAs($this->admin())
            ->test(EditAlbum::class, ['record' => $album->getRouteKey()])
            ->fillForm(['podcastSuggestions' => [$episode->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, DB::table('music_podcast_suggestions')
            ->where('suggestable_type', Album::class)
            ->where('suggestable_id', $album->id)
            ->where('podcast_episode_id', $episode->id)
            ->count());
    }

    public function test_selection_order_is_preserved_as_sort_order(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $first = $this->episode(['title' => 'First']);
        $second = $this->episode(['title' => 'Second', 'slug' => 'second']);
        $third = $this->episode(['title' => 'Third', 'slug' => 'third']);

        Livewire::actingAs($this->admin())
            ->test(EditAlbum::class, ['record' => $album->getRouteKey()])
            ->fillForm(['podcastSuggestions' => [$third->id, $first->id, $second->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $album->refresh();
        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $album->podcastSuggestions->pluck('id')->all(),
        );
    }

    public function test_the_frontend_displays_the_selected_podcast_suggestion(): void
    {
        $album = $this->album(['title' => 'Quiet Mornings', 'status' => ReleaseStatus::Published]);
        $episode = $this->episode(['title' => 'A Distinctive Episode Title']);
        $album->podcastSuggestions()->sync([$episode->id => ['sort_order' => 0]]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertSee('You May Also Like — Podcast Episodes');
        $response->assertSee('A Distinctive Episode Title');
        $response->assertSee(route('podcast.episodes.show', $episode), false);
    }

    public function test_the_frontend_does_not_render_an_empty_suggestion_section(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertDontSee('You May Also Like — Podcast Episodes');
    }

    public function test_the_frontend_hides_the_section_entirely_when_disabled(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $episode = $this->episode(['title' => 'Should Stay Hidden']);
        $album->podcastSuggestions()->sync([$episode->id => ['sort_order' => 0]]);

        config(['features.podcast_suggestions_enabled' => false]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertDontSee('You May Also Like — Podcast Episodes');
        $response->assertDontSee('Should Stay Hidden');
    }

    /**
     * The whole point of a feature flag, not a data-destructive switch —
     * turning it off must never touch the stored pivot rows, and turning it
     * back on must make them immediately visible again with no
     * reconfiguration.
     */
    public function test_disabling_and_re_enabling_the_flag_never_touches_stored_relationships(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $episode = $this->episode(['title' => 'Survives The Flag']);
        $album->podcastSuggestions()->sync([$episode->id => ['sort_order' => 0]]);

        config(['features.podcast_suggestions_enabled' => false]);
        $this->get(route('music.albums.show', $album))->assertDontSee('Survives The Flag');

        config(['features.podcast_suggestions_enabled' => true]);
        $response = $this->get(route('music.albums.show', $album));
        $response->assertOk();
        $response->assertSee('Survives The Flag');

        $album->refresh();
        $this->assertCount(1, $album->podcastSuggestions);
    }

    public function test_an_unpublished_episode_suggestion_is_not_rendered(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $episode = $this->episode(['title' => 'Draft Episode', 'status' => PodcastEpisodeStatus::Draft]);
        $album->podcastSuggestions()->sync([$episode->id => ['sort_order' => 0]]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertDontSee('Draft Episode');
        $response->assertDontSee('You May Also Like — Podcast Episodes');
    }

    public function test_an_episode_whose_podcast_show_is_unpublished_is_not_rendered(): void
    {
        $podcast = Podcast::query()->create(['title' => 'Unpublished Show', 'slug' => 'unpublished-show', 'status' => PodcastStatus::Draft]);
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $episode = $this->episode(['title' => 'Orphaned Episode'], $podcast);
        $album->podcastSuggestions()->sync([$episode->id => ['sort_order' => 0]]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertDontSee('Orphaned Episode');
    }

    /**
     * A deleted episode simply vanishes from the loaded collection
     * (cascadeOnDelete on the pivot's podcast_episode_id) rather than
     * throwing — the page must still render normally.
     */
    public function test_a_deleted_episode_fails_safely_and_the_page_still_renders(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $episode = $this->episode(['title' => 'Soon Deleted']);
        $album->podcastSuggestions()->sync([$episode->id => ['sort_order' => 0]]);
        $episode->forceDelete();

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertDontSee('Soon Deleted');
    }

    public function test_an_album_owned_track_page_shows_the_parent_albums_podcast_suggestions(): void
    {
        $album = $this->album(['title' => 'Quiet Mornings', 'status' => ReleaseStatus::Published]);
        $track = Track::query()->create([
            'album_id' => $album->id,
            'title' => 'Here I Am',
            'slug' => 'here-i-am-'.uniqid(),
            'track_number' => 1,
            'status' => TrackStatus::Published,
        ]);
        $episode = $this->episode(['title' => 'Track Page Suggestion']);
        $album->podcastSuggestions()->sync([$episode->id => ['sort_order' => 0]]);

        $response = $this->get(route('music.tracks.show', $track));

        $response->assertOk();
        $response->assertSee('Track Page Suggestion');
    }

    /**
     * REGRESSION — Featured Album/Single (is_featured) is a completely
     * separate, untouched mechanism from these curated Podcast suggestions.
     */
    public function test_featured_release_selection_is_unaffected_by_podcast_suggestions(): void
    {
        $featured = $this->album(['title' => 'Still Featured', 'status' => ReleaseStatus::Published, 'is_featured' => true]);
        $episode = $this->episode();
        $featured->podcastSuggestions()->sync([$episode->id => ['sort_order' => 0]]);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSeeText('Featured Release');
        $response->assertSee('Still Featured');
    }

    /**
     * REGRESSION — the landing-page autoplay Track feature (a totally
     * separate admin-picked Track) must be unaffected by anything here.
     */
    public function test_landing_page_autoplay_is_unaffected_by_podcast_suggestions(): void
    {
        config(['features.music_landing_autoplay_enabled' => true]);
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = Media::query()->create([
            'disk' => 'local',
            'path' => 'media/audio/regression-test.mp3',
            'original_filename' => 'regression-test.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 17,
            'visibility' => 'protected',
        ]);
        $track = Track::query()->create([
            'album_id' => $album->id,
            'title' => 'Autoplay Track',
            'slug' => 'autoplay-track-'.uniqid(),
            'track_number' => 1,
            'status' => TrackStatus::Published,
            'audio_media_id' => $media->id,
        ]);
        app(SettingsRepository::class)->set('music', 'landing_autoplay_track_id', $track->id, 'integer');

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee('data-music-autoplay-audio', false);
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
    private function album(array $overrides = []): Album
    {
        return Album::query()->create([
            'title' => 'An Album',
            'slug' => 'an-album-'.uniqid(),
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function single(array $overrides = []): Single
    {
        return Single::query()->create([
            'title' => 'A Single',
            'slug' => 'a-single-'.uniqid(),
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function episode(array $overrides = [], ?Podcast $podcast = null): PodcastEpisode
    {
        $podcast ??= Podcast::query()->create([
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
}
