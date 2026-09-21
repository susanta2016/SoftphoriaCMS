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
use App\Modules\Music\Support\LandingAutoplayTrackResolver;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * The Music landing page's autoplay enhancement: an admin picks exactly one
 * Track via Website Setup > Music > Landing Page Autoplay
 * (MusicLandingAutoplaySettings, group "music" in the existing `settings`
 * table), completely independent of Featured Album/Single. The public page
 * plays that one Track invisibly — no visible player controls.
 *
 * Revised spec, 2026-09-16: this one configured Track is exempt from every
 * other listening restriction — no guest preview cutoff, no registered
 * daily-listen quota, and no completion beacon at all — served by the
 * dedicated music.landing-autoplay.stream route
 * (MusicLandingAutoplayStreamController), never the shared
 * music.tracks.stream/music.tracks.listen-complete routes every other Track
 * still uses unchanged (see Tests\Feature\Music\TrackStreamControllerTest).
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

        // Faked once per test here rather than inside audioMedia() below —
        // Storage::fake() resets the fake disk on every call, which would
        // silently wipe out a file from an earlier audioMedia() call in the
        // same test (several tests here create more than one).
        Storage::fake('local');
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
            ->fillForm(['tracks' => [['track_id' => $track->id]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$track->id], app(LandingAutoplayTrackResolver::class)->configuredIds(app(SettingsRepository::class)));
    }

    public function test_admin_can_clear_the_autoplay_track(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['status' => TrackStatus::Published]);
        $this->setAutoplayTrack($track);

        Livewire::actingAs($this->admin())
            ->test(MusicLandingAutoplaySettings::class)
            ->fillForm(['tracks' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], app(LandingAutoplayTrackResolver::class)->configuredIds(app(SettingsRepository::class)));
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

        $this->setAutoplayTrack($track);

        $raw = Setting::query()->where('group', 'music')->where('key', 'landing_autoplay_track_ids')->first();

        $this->assertSame('['.$track->id.']', $raw->value);
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
        $response->assertSee('src="'.route('music.landing-autoplay.stream').'"', false);
        $response->assertSee('🎵 Now Playing', false);
        $response->assertSee('Configured Track');
        $response->assertSee('Stop Music', false);
    }

    /**
     * The core behavior change from the earlier Featured-based design: the
     * Featured Album/Single (is_featured = true) must never drive autoplay
     * — only the explicitly configured Track does, even when they differ.
     * The autoplay <audio> src is track-agnostic (always
     * music.landing-autoplay.stream, with no track identifier in the URL),
     * so this is verified by what the stream endpoint actually serves —
     * see test_the_stream_endpoint_serves_the_configured_tracks_audio_bytes.
     */
    public function test_the_landing_page_never_uses_the_featured_release_as_the_autoplay_source(): void
    {
        $featuredAlbum = $this->album(['title' => 'Featured Album', 'status' => ReleaseStatus::Published, 'is_featured' => true]);
        $featuredMedia = $this->audioMedia();
        $this->track($featuredAlbum, null, ['title' => 'Featured Track', 'status' => TrackStatus::Published, 'track_number' => 1, 'audio_media_id' => $featuredMedia->id]);

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
        $response->assertSee('src="'.route('music.landing-autoplay.stream').'"', false);
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
        app(SettingsRepository::class)->set('music', 'landing_autoplay_track_ids', '[999999]');

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

    /**
     * Revised spec, 2026-09-16: unlike every other Track on the site, the
     * landing page's autoplay track is exempt from the registered
     * daily-listen quota — a member who already exhausted their quota
     * elsewhere still gets full autoplay here.
     */
    public function test_a_registered_user_at_their_daily_limit_still_gets_the_autoplay_source(): void
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
        $response->assertSee('data-music-autoplay-audio', false);
        $response->assertSee('src="'.route('music.landing-autoplay.stream').'"', false);
    }

    /**
     * Revised spec, 2026-09-16: a dedicated, unrestricted route — never the
     * shared music.tracks.stream every other Track (including this same
     * one played from its own Album page) still uses with its guest/quota
     * limits intact. See MusicLandingAutoplayStreamController's own
     * docblock for why this isn't a bypass flag on that shared route
     * instead.
     */
    public function test_the_autoplay_source_is_the_dedicated_unrestricted_stream_route(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee(route('music.landing-autoplay.stream'), false);
        $response->assertDontSee(route('music.tracks.stream', $track), false);
    }

    /**
     * No completion beacon at all, for guest or registered — this playback
     * must never count toward anyone's daily-listen quota (revised spec,
     * 2026-09-16).
     */
    public function test_no_completion_beacon_is_ever_rendered(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $guestResponse = $this->get(route('music.index'));
        $guestResponse->assertOk();
        $guestResponse->assertDontSee('data-music-autoplay-complete-url', false);
        $guestResponse->assertDontSee(route('music.tracks.listen-complete', $track), false);

        $user = User::factory()->create();
        $registeredResponse = $this->actingAs($user)->get(route('music.index'));
        $registeredResponse->assertOk();
        $registeredResponse->assertDontSee('data-music-autoplay-complete-url', false);
        $registeredResponse->assertDontSee(route('music.tracks.listen-complete', $track), false);
    }

    public function test_the_stream_endpoint_serves_the_configured_tracks_full_audio_bytes_to_a_guest(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id, 'duration_seconds' => 5]);
        $this->setAutoplayTrack($track);
        config(['features.guest_user_listening_limit_seconds' => 1]);

        $response = $this->get(route('music.landing-autoplay.stream'));

        $response->assertOk();
        // BinaryFileResponse (Range-capable, streamed directly rather than
        // buffered) — getContent() is not the raw body in tests, same as
        // TrackStreamControllerTest's own registered-user assertions;
        // assert the response actually targets the full, untruncated file
        // on disk instead, despite the 1-second guest limit configured
        // above (a guest would be hard-truncated to a fraction of a second
        // on the shared music.tracks.stream route).
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(Storage::disk($media->disk)->path($media->path), $response->baseResponse->getFile()->getPathname());
        $this->assertSame(strlen('fake-audio-bytes'), $response->baseResponse->getFile()->getSize());
    }

    public function test_the_stream_endpoint_serves_the_full_file_to_a_registered_user_at_their_daily_limit(): void
    {
        config(['features.registered_user_whole_song_listens_per_day' => 1]);
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);

        $user = User::factory()->create();
        TrackListen::query()->create(['user_id' => $user->id, 'track_id' => $track->id]);

        $response = $this->actingAs($user)->get(route('music.landing-autoplay.stream'));

        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(Storage::disk($media->disk)->path($media->path), $response->baseResponse->getFile()->getPathname());
    }

    /**
     * The endpoint takes no track identifier from the request at all — it
     * always serves whichever track is currently configured, so there is
     * no way to request "the autoplay bypass" for an arbitrary track.
     */
    public function test_the_stream_endpoint_ignores_any_request_input_and_always_serves_the_configured_track(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $configuredMedia = $this->audioMedia();
        $configuredTrack = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $configuredMedia->id]);
        $this->setAutoplayTrack($configuredTrack);

        $otherMedia = $this->audioMedia('other-track.mp3', 'different-audio-bytes');
        $otherTrack = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $otherMedia->id]);

        $response = $this->get(route('music.landing-autoplay.stream', ['track' => $otherTrack->id]));

        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(Storage::disk($configuredMedia->disk)->path($configuredMedia->path), $response->baseResponse->getFile()->getPathname());
    }

    public function test_the_stream_endpoint_404s_when_no_track_is_configured(): void
    {
        $response = $this->get(route('music.landing-autoplay.stream'));

        $response->assertNotFound();
    }

    public function test_the_stream_endpoint_404s_when_the_master_switch_is_off(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $media = $this->audioMedia();
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $media->id]);
        $this->setAutoplayTrack($track);
        config(['features.music_landing_autoplay_enabled' => false]);

        $response = $this->get(route('music.landing-autoplay.stream'));

        $response->assertNotFound();
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

    public function test_multiple_configured_tracks_render_as_an_ordered_playlist(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $first = $this->track($album, null, ['title' => 'First Song', 'status' => TrackStatus::Published, 'audio_media_id' => $this->audioMedia('a.mp3')->id]);
        $second = $this->track($album, null, ['title' => 'Second Song', 'status' => TrackStatus::Published, 'audio_media_id' => $this->audioMedia('b.mp3')->id]);
        // Deliberately reversed relative to creation order.
        $this->setAutoplayTrack($second, $first);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee('data-playlist=', false);
        $response->assertSee('src="'.route('music.landing-autoplay.stream').'"', false);
        $response->assertSee(e(json_encode(['title' => 'First Song', 'src' => route('music.landing-autoplay.stream', ['position' => 1])])), false);
        $this->assertSame(
            ['Second Song', 'First Song'],
            app(LandingAutoplayTrackResolver::class)->resolve(app(SettingsRepository::class))->pluck('title')->all(),
        );
    }

    public function test_the_stream_endpoint_serves_the_track_at_the_requested_playlist_position(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $firstMedia = $this->audioMedia('a.mp3');
        $secondMedia = $this->audioMedia('b.mp3', 'second-audio-bytes');
        $first = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $firstMedia->id]);
        $second = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $secondMedia->id]);
        $this->setAutoplayTrack($first, $second);
        config(['features.guest_user_listening_limit_seconds' => 1]);

        $response = $this->get(route('music.landing-autoplay.stream', ['position' => 1]));

        $response->assertOk();
        $this->assertSame(Storage::disk($secondMedia->disk)->path($secondMedia->path), $response->baseResponse->getFile()->getPathname());
    }

    public function test_the_stream_endpoint_404s_for_a_position_outside_the_playlist(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $this->audioMedia()->id]);
        $this->setAutoplayTrack($track);

        $this->get(route('music.landing-autoplay.stream', ['position' => 1]))->assertNotFound();
    }

    public function test_unplayable_tracks_are_skipped_but_the_rest_of_the_playlist_still_plays(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $draft = $this->track($album, null, ['title' => 'Draft Song', 'status' => TrackStatus::Draft, 'audio_media_id' => $this->audioMedia('a.mp3')->id]);
        $silent = $this->track($album, null, ['title' => 'Silent Song', 'status' => TrackStatus::Published]);
        $good = $this->track($album, null, ['title' => 'Good Song', 'status' => TrackStatus::Published, 'audio_media_id' => $this->audioMedia('b.mp3')->id]);
        $this->setAutoplayTrack($draft, $silent, $good);

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee('Good Song');
        $response->assertDontSee('Draft Song');
        $response->assertDontSee('Silent Song');
        // The one survivor is position 0, so the stream URL carries no position.
        $response->assertSee('src="'.route('music.landing-autoplay.stream').'"', false);
    }

    public function test_the_earlier_single_track_setting_still_works_as_a_one_item_playlist(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['title' => 'Legacy Song', 'status' => TrackStatus::Published, 'audio_media_id' => $this->audioMedia()->id]);
        app(SettingsRepository::class)->set('music', 'landing_autoplay_track_id', $track->id, 'integer');

        $response = $this->get(route('music.index'));

        $response->assertOk();
        $response->assertSee('Legacy Song');
        $response->assertSee('data-music-autoplay-audio', false);
    }

    public function test_an_explicitly_emptied_playlist_does_not_fall_back_to_the_earlier_single_track_setting(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['status' => TrackStatus::Published, 'audio_media_id' => $this->audioMedia()->id]);
        app(SettingsRepository::class)->set('music', 'landing_autoplay_track_id', $track->id, 'integer');
        app(SettingsRepository::class)->set('music', 'landing_autoplay_track_ids', '[]');

        $this->get(route('music.index'))->assertDontSee('data-music-autoplay-audio', false);
    }

    private function setAutoplayTrack(Track ...$tracks): void
    {
        app(SettingsRepository::class)->set('music', 'landing_autoplay_track_ids', json_encode(array_map(fn (Track $track): int => $track->id, $tracks)));
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    private function audioMedia(string $filename = 'test-track.mp3', string $contents = 'fake-audio-bytes'): Media
    {
        Storage::disk('local')->put("media/audio/{$filename}", $contents);

        return Media::query()->create([
            'disk' => 'local',
            'path' => "media/audio/{$filename}",
            'original_filename' => $filename,
            'mime_type' => 'audio/mpeg',
            'size' => strlen($contents),
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
