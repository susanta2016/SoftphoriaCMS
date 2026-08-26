<?php

namespace Tests\Feature\Music;

use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Models\Media;
use App\Models\Page;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Lyrics;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\SongStory;
use App\Modules\Music\Models\Track;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Public Music landing/catalogue + Album/Single listening pages — fully
 * public once Published, no membership/entitlement gate on viewing, same
 * shape as Poetry/Prose's own test suite.
 */
class MusicControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_page_lists_published_albums_and_singles(): void
    {
        $album = $this->album(['title' => 'Published Album', 'status' => ReleaseStatus::Published]);
        $draftAlbum = $this->album(['title' => 'Draft Album', 'slug' => 'draft-album', 'status' => ReleaseStatus::Draft]);
        $single = $this->single(['title' => 'Published Single', 'status' => ReleaseStatus::Published]);
        $archivedSingle = $this->single(['title' => 'Archived Single', 'slug' => 'archived-single', 'status' => ReleaseStatus::Archived]);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee('Published Album');
        $response->assertSee('Published Single');
        $response->assertDontSee('Draft Album');
        $response->assertDontSee('Archived Single');
    }

    public function test_the_index_page_search_filters_by_title(): void
    {
        $this->album(['title' => 'Here I Am', 'slug' => 'here-i-am', 'status' => ReleaseStatus::Published]);
        $this->album(['title' => 'Return to Center', 'slug' => 'return-to-center', 'status' => ReleaseStatus::Published]);

        $response = $this->get(route('music.index', ['q' => 'Here I Am']));

        $response->assertOk();
        $response->assertSee('Here I Am');
        $response->assertDontSee('Return to Center');
    }

    public function test_the_index_page_type_filter_shows_only_albums(): void
    {
        $this->album(['title' => 'An Album', 'status' => ReleaseStatus::Published]);
        $this->single(['title' => 'A Single', 'status' => ReleaseStatus::Published]);

        $response = $this->get(route('music.index', ['type' => 'album']));

        $response->assertOk();
        $response->assertSee('An Album');
        $response->assertDontSee('A Single');
    }

    public function test_an_ajax_request_returns_only_the_catalogue_fragment(): void
    {
        $this->album(['title' => 'Ajax Album', 'status' => ReleaseStatus::Published]);

        $response = $this->get(route('music.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertSee('Ajax Album');
        $response->assertSee('The Catalogue');
        $response->assertDontSee('Featured Release');
        $response->assertDontSee('<!DOCTYPE html>', false);
        $response->assertDontSee('aria-label="Primary"', false);
    }

    public function test_a_normal_request_returns_the_full_page(): void
    {
        $this->album(['title' => 'Full Page Album', 'status' => ReleaseStatus::Published]);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee('Full Page Album');
        $response->assertSee('<!DOCTYPE html>', false);
        $response->assertSee('aria-label="Primary"', false);
    }

    public function test_an_ajax_request_respects_the_type_filter_and_search(): void
    {
        $this->album(['title' => 'Matching Album', 'status' => ReleaseStatus::Published]);
        $this->single(['title' => 'Non Matching Single', 'status' => ReleaseStatus::Published]);

        $response = $this->get(route('music.index', ['type' => 'album', 'q' => 'Matching']), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertSee('Matching Album');
        $response->assertDontSee('Non Matching Single');
    }

    public function test_the_index_page_paginates_server_side(): void
    {
        foreach (range(1, 13) as $i) {
            $this->album(['title' => "Album {$i}", 'slug' => "album-{$i}", 'status' => ReleaseStatus::Published, 'release_date' => now()->subDays($i)]);
        }

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee('Album 1');
        $response->assertDontSee('Album 13');

        $response = $this->get(route('music.index', ['page' => 2]));
        $response->assertOk();
        $response->assertSee('Album 13');
    }

    public function test_only_a_featured_album_or_single_appears_as_the_featured_release(): void
    {
        $featured = $this->album(['title' => 'Featured One', 'status' => ReleaseStatus::Published, 'is_featured' => true, 'release_date' => now()]);
        $this->single(['title' => 'Not Featured', 'status' => ReleaseStatus::Published, 'is_featured' => false, 'release_date' => now()->addDay()]);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSeeText('Featured Release');
        $response->assertSee('Featured One');
    }

    public function test_no_featured_release_section_when_nothing_is_flagged(): void
    {
        $this->album(['title' => 'Ordinary Album', 'status' => ReleaseStatus::Published, 'is_featured' => false]);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertDontSeeText('Featured Release');
    }

    public function test_hero_and_story_banner_images_come_from_the_music_pages_hero_sections(): void
    {
        $heroMedia = $this->image();
        $storyMedia = $this->image();

        $page = Page::query()->create([
            'title' => 'Music',
            'slug' => 'music',
            'template' => PageTemplate::Custom,
            'status' => PageStatus::Published,
        ]);
        $page->sections()->create(['section_type' => PageSectionType::Hero->value, 'sort_order' => 0, 'content_json' => ['media_id' => $heroMedia->id]]);
        $page->sections()->create(['section_type' => PageSectionType::Hero->value, 'sort_order' => 1, 'content_json' => ['media_id' => $storyMedia->id]]);

        $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $this->single(['status' => ReleaseStatus::Published]), ['status' => TrackStatus::Published]);
        SongStory::query()->create(['track_id' => $track->id, 'content' => 'A real song story.']);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee(Storage::disk($heroMedia->disk)->url($heroMedia->path), false);
        $response->assertSee(Storage::disk($storyMedia->disk)->url($storyMedia->path), false);
    }

    public function test_the_listening_page_reuses_the_music_landing_pages_top_banner(): void
    {
        $bannerMedia = $this->image();

        $page = Page::query()->create([
            'title' => 'Music',
            'slug' => 'music',
            'template' => PageTemplate::Custom,
            'status' => PageStatus::Published,
        ]);
        $page->sections()->create(['section_type' => PageSectionType::Hero->value, 'sort_order' => 0, 'content_json' => ['media_id' => $bannerMedia->id]]);

        $album = $this->album(['status' => ReleaseStatus::Published]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertSee(Storage::disk($bannerMedia->disk)->url($bannerMedia->path), false);
    }

    public function test_music_index_renders_without_a_music_page_at_all(): void
    {
        $this->album(['status' => ReleaseStatus::Published]);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee('Songs written in the language of light.');
    }

    public function test_a_published_album_listening_page_resolves(): void
    {
        $album = $this->album(['title' => 'Quiet Mornings', 'status' => ReleaseStatus::Published]);
        $this->track($album, null, ['title' => 'Here I Am', 'status' => TrackStatus::Published, 'track_number' => 1]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertSee('Quiet Mornings');
        $response->assertSee('Here I Am');
    }

    public function test_the_album_page_never_shows_a_video_section_even_with_a_legacy_embed_url(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published, 'embed_video_url' => 'https://www.youtube.com/watch?v=abc123']);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertDontSee('data-video-modal-toggle', false);
    }

    public function test_a_single_pages_hero_never_shows_external_streaming_links(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $single->streamingLinks()->create(['provider' => 'spotify', 'url' => 'https://open.spotify.com/track/xyz', 'sort_order' => 0]);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertDontSee('Spotify');
    }

    public function test_an_albums_hero_still_shows_external_streaming_links(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $album->streamingLinks()->create(['provider' => 'spotify', 'url' => 'https://open.spotify.com/album/xyz', 'sort_order' => 0]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertSee('Spotify');
    }

    public function test_a_single_pages_hero_shows_a_video_button_when_a_track_has_an_embed_url(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $this->track(null, $single, ['status' => TrackStatus::Published, 'video_embed_url' => 'https://www.youtube.com/watch?v=abc123']);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertSee('data-video-modal-toggle', false);
        $response->assertSee('youtube.com/embed/abc123', false);
    }

    public function test_a_single_pages_hero_hides_the_video_button_when_no_embed_url_is_set(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $this->track(null, $single, ['status' => TrackStatus::Published]);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertDontSee('data-video-modal-toggle', false);
    }

    public function test_a_draft_album_404s_publicly(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Draft]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertNotFound();
    }

    public function test_an_archived_album_404s_publicly(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Archived]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertNotFound();
    }

    public function test_a_draft_track_does_not_appear_in_its_published_albums_track_list(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $this->track($album, null, ['title' => 'Visible Track', 'status' => TrackStatus::Published, 'track_number' => 1]);
        $this->track($album, null, ['title' => 'Hidden Draft Track', 'slug' => 'hidden-draft-track', 'status' => TrackStatus::Draft, 'track_number' => 2]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertSee('Visible Track');
        $response->assertDontSee('Hidden Draft Track');
    }

    public function test_an_albums_track_list_links_to_the_tracks_own_page(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['title' => 'Here I Am', 'status' => TrackStatus::Published, 'track_number' => 1]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertSee(route('music.tracks.show', $track), false);
    }

    public function test_an_album_owned_tracks_page_resolves_with_the_parent_albums_context(): void
    {
        $album = $this->album(['title' => 'Quiet Mornings', 'status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['title' => 'Here I Am', 'status' => TrackStatus::Published, 'description' => '<p>A gentle song.</p>']);

        $response = $this->get(route('music.tracks.show', $track));

        $response->assertOk();
        $response->assertSee('Here I Am');
        $response->assertSee('Quiet Mornings');
        $response->assertSee('A gentle song.');
        $response->assertDontSee('&lt;p&gt;', false);
    }

    public function test_a_draft_tracks_page_404s_publicly(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['status' => TrackStatus::Draft]);

        $response = $this->get(route('music.tracks.show', $track));

        $response->assertNotFound();
    }

    public function test_a_track_belonging_to_an_unpublished_album_404s_on_its_own_page(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Draft]);
        $track = $this->track($album, null, ['status' => TrackStatus::Published]);

        $response = $this->get(route('music.tracks.show', $track));

        $response->assertNotFound();
    }

    public function test_a_single_owned_tracks_page_redirects_to_the_single(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);

        $response = $this->get(route('music.tracks.show', $track));

        $response->assertRedirect(route('music.singles.show', $single));
    }

    public function test_sitemap_includes_an_album_owned_published_track(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['status' => TrackStatus::Published]);

        $response = $this->get('/sitemap.xml');

        $response->assertSee(route('music.tracks.show', $track), false);
    }

    public function test_sitemap_excludes_a_single_owned_track(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('music.tracks.show', $track), false);
    }

    public function test_the_featured_release_description_never_shows_raw_html(): void
    {
        $this->album(['title' => 'Featured With HTML', 'status' => ReleaseStatus::Published, 'is_featured' => true, 'description' => '<p>Rich <strong>description</strong>.</p>']);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee('Rich description.');
        $response->assertDontSee('&lt;p&gt;', false);
        $response->assertDontSee('&lt;strong&gt;', false);
    }

    public function test_a_published_single_listening_page_resolves(): void
    {
        $single = $this->single(['title' => 'Presence', 'status' => ReleaseStatus::Published]);
        $this->track(null, $single, ['title' => 'Presence', 'status' => TrackStatus::Published]);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertSee('Presence');
    }

    public function test_lyrics_only_shown_when_public(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);
        Lyrics::query()->create(['track_id' => $track->id, 'content' => 'These are the public lyrics', 'visibility' => 'public']);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertSee('These are the public lyrics');
    }

    public function test_private_lyrics_are_never_shown_publicly(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);
        Lyrics::query()->create(['track_id' => $track->id, 'content' => 'These are secret private lyrics', 'visibility' => 'private']);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertDontSee('These are secret private lyrics');
    }

    public function test_a_published_album_is_never_marked_noindex_by_default(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertDontSee('noindex', false);
    }

    public function test_sitemap_includes_a_published_album_and_single(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $single = $this->single(['status' => ReleaseStatus::Published]);

        $response = $this->get('/sitemap.xml');

        $response->assertSee(route('music.albums.show', $album), false);
        $response->assertSee(route('music.singles.show', $single), false);
    }

    public function test_sitemap_excludes_a_draft_album(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Draft]);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('music.albums.show', $album), false);
    }

    public function test_sitemap_excludes_an_album_explicitly_marked_noindex(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $album->seo()->create(['robots' => 'noindex, nofollow']);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('music.albums.show', $album), false);
    }

    public function test_sitemap_includes_the_music_landing_page(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertSee(route('music.index'), false);
    }

    public function test_track_stream_404s_for_a_track_with_no_audio_file(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);

        $response = $this->get(route('music.tracks.stream', $track));

        $response->assertNotFound();
    }

    public function test_track_stream_404s_for_a_draft_track(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track(null, $single, ['status' => TrackStatus::Draft, 'audio_media_id' => $media->id]);

        $response = $this->get(route('music.tracks.stream', $track));

        $response->assertNotFound();
    }

    public function test_track_stream_404s_when_the_parent_release_is_not_published(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Draft]);
        $media = $this->audioMedia();
        $track = $this->track(null, $single, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);

        $response = $this->get(route('music.tracks.stream', $track));

        $response->assertNotFound();
    }

    public function test_track_stream_serves_the_audio_file_when_everything_is_published(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track(null, $single, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);

        $response = $this->get(route('music.tracks.stream', $track));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/mpeg');
    }

    public function test_track_stream_404s_when_native_playback_is_disabled(): void
    {
        app(SettingsRepository::class)->set('music', 'native_playback_enabled', false, 'boolean');

        $single = $this->single(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track(null, $single, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);

        $response = $this->get(route('music.tracks.stream', $track));

        $response->assertNotFound();
    }

    private function audioMedia(): Media
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/audio/test-track.mp3', 'fake-audio-bytes');

        return Media::query()->create([
            'disk' => 'local',
            'path' => 'media/audio/test-track.mp3',
            'original_filename' => 'test-track.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 17,
            'visibility' => 'protected',
        ]);
    }

    private function image(): Media
    {
        Storage::fake('public');
        $filename = 'test-banner-'.uniqid().'.png';
        Storage::disk('public')->put('media/images/'.$filename, 'fake-image-bytes');

        return Media::query()->create([
            'disk' => 'public',
            'path' => 'media/images/'.$filename,
            'original_filename' => $filename,
            'mime_type' => 'image/png',
            'size' => 17,
            'visibility' => 'public',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function album(array $overrides = []): Album
    {
        return Album::query()->create([
            'title' => 'An Album',
            'slug' => 'an-album',
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
            'slug' => 'a-single',
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function track(?Album $album, ?Single $single, array $overrides = []): Track
    {
        return Track::query()->create([
            'album_id' => $album?->id,
            'single_id' => $single?->id,
            'title' => 'A Track',
            'slug' => 'a-track-'.uniqid(),
            'status' => TrackStatus::Draft,
            ...$overrides,
        ]);
    }
}
