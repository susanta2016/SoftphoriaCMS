<?php

namespace Tests\Feature\Music;

use App\Models\Media;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Filament\Pages\MusicLandingAutoplaySettings;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Modules\Music\Models\TrackListen;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Music landing page's autoplay enhancement (revised spec, 2026-09-13):
 * an admin picks exactly one Track via Website Setup > Music > Landing Page
 * Autoplay (MusicLandingAutoplaySettings, group "music" in the existing
 * `settings` table), completely independent of Featured Album/Single. The
 * public page plays that one Track invisibly — no visible player controls —
 * through the existing native music.tracks.stream/music.tracks.listen-
 * complete routes (TrackStreamController/TrackListenController, untouched).
 * See resources/js/app.js's dedicated [data-music-autoplay-*] block.
 */
class MusicLandingAutoplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // config('features.music_landing_autoplay_enabled') defaults to
        // false site-wide (config/features.php) until an admin explicitly
        // turns it on via MUSIC_LANDING_AUTOPLAY_ENABLED — every test here
        // exercises the feature's real behavior, so it's switched on for
        // the whole class; the two tests below cover the "master switch
        // off" case explicitly.
        config(['features.music_landing_autoplay_enabled' => true]);
    }

    public function test_the_master_switch_being_off_disables_autoplay_even_with_a_configured_track(): void
    {
        config(['features.music_landing_autoplay_enabled' => false]);
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertDontSee('data-music-autoplay-audio', false);
    }

    public function test_the_admin_settings_page_is_hidden_from_navigation_when_the_master_switch_is_off(): void
    {
        config(['features.music_landing_autoplay_enabled' => false]);

        $this->assertFalse(MusicLandingAutoplaySettings::shouldRegisterNavigation());
    }

    public function test_the_admin_settings_page_is_visible_in_navigation_when_the_master_switch_is_on(): void
    {
        $this->assertTrue(MusicLandingAutoplaySettings::shouldRegisterNavigation());
    }

    public function test_admin_can_select_an_autoplay_track(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['title' => 'Morning Light', 'status' => TrackStatus::Published]);

        Livewire::actingAs($this->admin())
            ->test(MusicLandingAutoplaySettings::class)
            ->fillForm(['track_id' => $track->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($track->id, app(SettingsRepository::class)->get('music', 'landing_autoplay_track_id'));
    }

    public function test_admin_can_clear_the_autoplay_track(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['status' => TrackStatus::Published]);
        app(SettingsRepository::class)->set('music', 'landing_autoplay_track_id', $track->id, 'integer');

        Livewire::actingAs($this->admin())
            ->test(MusicLandingAutoplaySettings::class)
            ->fillForm(['track_id' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(app(SettingsRepository::class)->get('music', 'landing_autoplay_track_id'));
    }

    public function test_non_admin_cannot_access_the_autoplay_settings_page(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/music/landing-autoplay');

        $response->assertForbidden();
    }

    /**
     * The setting stores only the Track's integer id — never a URL, media
     * path, or filename. Asserted directly against the raw settings table.
     */
    public function test_the_selected_track_is_stored_by_id_not_by_url_or_path(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);

        app(SettingsRepository::class)->set('music', 'landing_autoplay_track_id', $track->id, 'integer');

        $raw = Setting::query()->where('group', 'music')->where('key', 'landing_autoplay_track_id')->first();

        $this->assertSame((string) $track->id, $raw->value);
        $this->assertStringNotContainsString('/', $raw->value);
        $this->assertStringNotContainsString('.mp3', $raw->value);
    }

    public function test_the_landing_page_resolves_and_autoplays_the_configured_track(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['title' => 'Configured Track', 'status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee('data-music-autoplay-audio', false);
        $response->assertSee('src="'.route('music.tracks.stream', $track).'"', false);
        $response->assertSee('🎵 Now Playing', false);
        $response->assertSee('Configured Track');
        $response->assertSee('Stop Music', false);
    }

    /**
     * The core behavior change from the earlier Featured-based design: the
     * Featured Album/Single (is_featured = true) must never drive autoplay
     * — only the explicitly configured Track does, even when they differ.
     */
    public function test_the_landing_page_never_uses_the_featured_release_as_the_autoplay_source(): void
    {
        $featuredAlbum = $this->album(['title' => 'Featured Album', 'status' => ReleaseStatus::Published, 'is_featured' => true]);
        $featuredMedia = $this->audioMedia();
        $featuredTrack = $this->track($featuredAlbum, null, ['title' => 'Featured Track', 'status' => TrackStatus::Published, 'track_number' => 1, 'audio_media_id' => $featuredMedia->id]);

        $otherAlbum = $this->album(['title' => 'Other Album', 'slug' => 'other-album', 'status' => ReleaseStatus::Published, 'is_featured' => false]);
        $configuredMedia = $this->audioMedia();
        $configuredTrack = $this->track($otherAlbum, null, ['title' => 'Configured Autoplay Track', 'status' => TrackStatus::Published, 'audio_media_id' => $configuredMedia->id]);
        $this->setAutoplayTrack($configuredTrack);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        // Featured section still renders as before (unchanged).
        $response->assertSee('Featured Release');
        $response->assertSee('Featured Album');
        // But autoplay uses only the configured track, never the featured one.
        $response->assertSee('src="'.route('music.tracks.stream', $configuredTrack).'"', false);
        $response->assertDontSee('src="'.route('music.tracks.stream', $featuredTrack).'"', false);
    }

    public function test_no_configured_track_means_no_autoplay_markup_at_all(): void
    {
        $this->album(['status' => ReleaseStatus::Published, 'is_featured' => true]);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertDontSee('data-music-autoplay-audio', false);
        $response->assertDontSee('🎵 Now Playing', false);
        $response->assertDontSee('🎵 Music', false);
    }

    public function test_a_deleted_track_fails_safely_with_no_autoplay(): void
    {
        app(SettingsRepository::class)->set('music', 'landing_autoplay_track_id', 999999, 'integer');

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertDontSee('data-music-autoplay-audio', false);
    }

    public function test_an_unpublished_track_fails_safely_with_no_autoplay(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Draft, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertDontSee('data-music-autoplay-audio', false);
    }

    /**
     * A Track can stay status=published while its parent Album is archived
     * independently — the same double-check showTrack() already applies.
     */
    public function test_a_track_whose_parent_album_is_no_longer_published_fails_safely(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Archived]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertDontSee('data-music-autoplay-audio', false);
    }

    public function test_a_configured_track_with_no_uploaded_audio_renders_no_autoplay_markup(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['status' => TrackStatus::Published]);
        $this->setAutoplayTrack($track);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertDontSee('data-music-autoplay-audio', false);
    }

    public function test_a_registered_user_at_their_daily_limit_gets_no_autoplay_source(): void
    {
        config(['features.registered_user_whole_song_listens_per_day' => 5]);
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            TrackListen::query()->create(['user_id' => $user->id, 'track_id' => $track->id]);
        }

        $response = $this->actingAs($user)->get(route('music.index'));

        $response->assertOk();
        $response->assertDontSee('data-music-autoplay-audio', false);
    }

    public function test_the_autoplay_source_is_the_existing_native_stream_route_never_a_new_endpoint(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee(route('music.tracks.stream', $track), false);
    }

    /**
     * complete_url (the daily-quota completion beacon) is only ever
     * rendered for a registered user — a guest's autoplay listen is never
     * quota-tracked, same as the existing multi-track player.
     */
    public function test_the_completion_beacon_is_only_rendered_for_a_registered_user(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $guestResponse = $this->get(route('music.index'));
        $guestResponse->assertOk();
        $guestResponse->assertDontSee(route('music.tracks.listen-complete', $track), false);

        $user = User::factory()->create();
        $registeredResponse = $this->actingAs($user)->get(route('music.index'));
        $registeredResponse->assertOk();
        $registeredResponse->assertSee(route('music.tracks.listen-complete', $track), false);
    }

    public function test_no_visible_player_controls_are_rendered_for_the_autoplay_track(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertDontSee('data-music-player', false);
        $response->assertDontSee('data-music-track-row', false);
        $response->assertDontSee('data-music-player-play', false);
    }

    public function test_the_autoplay_markup_never_appears_on_a_non_music_page(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('data-music-autoplay-audio', false);
    }

    public function test_the_listening_page_is_unaffected_by_the_landing_pages_autoplay_track(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $albumTrack = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);

        $otherAlbum = $this->album(['status' => ReleaseStatus::Published, 'slug' => 'other-album']);
        $otherMedia = $this->audioMedia();
        $autoplayTrack = $this->track($otherAlbum, null, ['status' => TrackStatus::Published, 'audio_media_id' => $otherMedia->id]);
        $this->setAutoplayTrack($autoplayTrack);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertSee('data-music-player', false);
        $response->assertDontSee('data-music-autoplay-audio', false);
        $response->assertDontSee(route('music.tracks.stream', $autoplayTrack), false);
    }

    private function setAutoplayTrack(Track $track): void
    {
        app(SettingsRepository::class)->set('music', 'landing_autoplay_track_id', $track->id, 'integer');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
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
