<?php

namespace Tests\Feature\Music;

use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Models\Album;
use App\Shared\Support\Media\YoutubeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Album-level YouTube video field, restored to the admin form
 * (client-confirmed, 2026-09-05) after previously being hidden entirely —
 * see AlbumForm.php's own docblock. YouTube only: one URL field, no
 * uploaded video, no other provider. App\Shared\Support\Media\YoutubeUrl is
 * the single validity check both AlbumForm's admin-side rule and these
 * tests exercise, so this suite doubles as direct coverage of exactly what
 * the admin form accepts/rejects.
 */
class AlbumYoutubeVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_standard_watch_url_is_a_valid_youtube_url(): void
    {
        $this->assertTrue(YoutubeUrl::isValid('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_a_short_youtu_be_url_is_a_valid_youtube_url(): void
    {
        $this->assertTrue(YoutubeUrl::isValid('https://youtu.be/dQw4w9WgXcQ'));
    }

    public function test_an_embed_url_is_a_valid_youtube_url(): void
    {
        $this->assertTrue(YoutubeUrl::isValid('https://www.youtube.com/embed/dQw4w9WgXcQ'));
    }

    public function test_a_shorts_url_is_a_valid_youtube_url(): void
    {
        $this->assertTrue(YoutubeUrl::isValid('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
    }

    public function test_a_vimeo_url_is_rejected(): void
    {
        $this->assertFalse(YoutubeUrl::isValid('https://vimeo.com/12345678'));
    }

    public function test_arbitrary_embed_html_is_rejected(): void
    {
        $this->assertFalse(YoutubeUrl::isValid('<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'));
    }

    public function test_a_random_non_video_url_is_rejected(): void
    {
        $this->assertFalse(YoutubeUrl::isValid('https://example.com/some-page'));
    }

    public function test_a_blank_value_is_treated_as_no_url_rather_than_invalid(): void
    {
        $this->assertFalse(YoutubeUrl::isValid(null));
        $this->assertFalse(YoutubeUrl::isValid(''));
    }

    public function test_a_valid_url_resolves_to_the_expected_embed_url(): void
    {
        $this->assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0',
            YoutubeUrl::embedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
    }

    /**
     * Corrected 2026-09-05: the album's YouTube video no longer embeds
     * inline — it opens in the shared [data-video-modal] popup (the same
     * one the Track/Single "Watch video" icon uses) via a "Watch Video"
     * button. The iframe still carries the real embed URL, just as
     * data-src rather than src, so it never autoloads/autoplays before the
     * modal is opened (see resources/js/app.js's setOpen()).
     */
    public function test_the_album_page_renders_a_watch_video_button_when_configured(): void
    {
        $album = $this->album(['embed_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertSee('data-video-modal-toggle', false);
        $response->assertSee('Watch Video', false);
        $response->assertSee('youtube.com/embed/dQw4w9WgXcQ', false);
        $response->assertDontSee('data-album-video', false);
    }

    public function test_the_album_page_renders_no_video_button_when_no_url_is_configured(): void
    {
        $album = $this->album();

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertDontSee('data-video-modal-toggle', false);
    }

    /**
     * An invalid/unparseable stored value must degrade to "no player", never
     * a broken iframe with an empty or garbage src — mirrors the same
     * fail-safe the pre-existing Track/Single $embedUrl parser already
     * guarantees (a non-matching URL simply leaves $embedUrl null).
     */
    public function test_the_album_page_renders_no_video_button_when_the_stored_url_does_not_parse(): void
    {
        $album = $this->album(['embed_video_url' => 'https://vimeo.com/12345678']);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertDontSee('data-video-modal-toggle', false);
    }

    /**
     * The Watch Video button must appear before the Share buttons in
     * document order (it's one of the primary action buttons alongside
     * Play Now/Save, while Share stays a separate icon row) — asserted
     * directly on the rendered HTML rather than just "both are present",
     * since ordering is the actual requirement.
     */
    public function test_the_video_button_is_placed_before_the_share_buttons(): void
    {
        $album = $this->album(['embed_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $content = $response->getContent();

        $shareButtonsPosition = strpos($content, 'Share on Facebook');
        $videoPosition = strpos($content, 'data-video-modal-toggle');

        $this->assertNotFalse($shareButtonsPosition);
        $this->assertNotFalse($videoPosition);
        $this->assertLessThan($shareButtonsPosition, $videoPosition);
    }

    private function album(array $overrides = []): Album
    {
        return Album::query()->create([
            'title' => 'Album Video Test',
            'slug' => 'album-video-test-'.uniqid(),
            'status' => ReleaseStatus::Published,
            ...$overrides,
        ]);
    }
}
