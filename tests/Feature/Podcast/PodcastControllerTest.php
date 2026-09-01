<?php

namespace Tests\Feature\Podcast;

use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three public Podcast frontend pages — landing, All Episodes, and the
 * individual episode page — all fully public/unauthenticated, mirroring
 * PoetryProseControllerTest's shape. Nothing here is hardcoded: every
 * assertion reads back a value this test itself put in the database.
 */
class PodcastControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_loads_and_shows_database_driven_content(): void
    {
        $podcast = $this->podcast(['title' => 'A Distinctive Podcast Title '.uniqid()]);
        $episode = $this->episode($podcast, ['title' => 'A Distinctive Episode Title '.uniqid()]);

        $response = $this->get(route('podcast.index'));

        $response->assertOk();
        $response->assertSee($podcast->title);
        $response->assertSee($episode->title);
    }

    public function test_the_landing_page_omits_the_featured_section_gracefully_when_there_are_no_episodes(): void
    {
        $this->podcast();

        $response = $this->get(route('podcast.index'));

        $response->assertOk();
        $response->assertSee('No episodes published yet');
    }

    public function test_the_all_episodes_page_loads_and_lists_only_published_episodes(): void
    {
        $podcast = $this->podcast();
        $published = $this->episode($podcast, ['title' => 'Published Episode '.uniqid()]);
        $draft = $this->episode($podcast, ['title' => 'Draft Episode '.uniqid(), 'status' => PodcastEpisodeStatus::Draft]);

        $response = $this->get(route('podcast.episodes.index'));

        $response->assertOk();
        $response->assertSee($published->title);
        $response->assertDontSee($draft->title);
    }

    public function test_the_all_episodes_page_search_filters_by_title(): void
    {
        $podcast = $this->podcast();
        $match = $this->episode($podcast, ['title' => 'Zebra Migration Patterns '.uniqid()]);
        $other = $this->episode($podcast, ['title' => 'Unrelated Topic '.uniqid()]);

        $response = $this->get(route('podcast.episodes.index', ['q' => 'Zebra Migration']));

        $response->assertOk();
        $response->assertSee($match->title);
        $response->assertDontSee($other->title);
    }

    public function test_the_individual_episode_page_loads_and_shows_database_driven_content(): void
    {
        $podcast = $this->podcast();
        $episode = $this->episode($podcast, [
            'title' => 'A Very Specific Episode Title '.uniqid(),
            'description' => '<p>A distinctive description sentinel paragraph.</p>',
        ]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee($episode->title);
        $response->assertSee('A distinctive description sentinel paragraph.');
    }

    public function test_an_unpublished_episode_404s(): void
    {
        $podcast = $this->podcast();
        $episode = $this->episode($podcast, ['status' => PodcastEpisodeStatus::Draft]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertNotFound();
    }

    public function test_the_episode_page_embeds_the_youtube_video_inline_when_configured(): void
    {
        $podcast = $this->podcast();
        $episode = $this->episode($podcast, ['embed_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_the_episode_page_omits_the_video_area_when_no_video_url_is_configured(): void
    {
        $podcast = $this->podcast();
        $episode = $this->episode($podcast, ['embed_url' => null, 'artwork_media_id' => null]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertDontSee('<iframe', false);
    }

    private function podcast(array $overrides = []): Podcast
    {
        return Podcast::query()->create([
            'title' => 'Test Podcast '.uniqid(),
            'slug' => 'test-podcast-'.uniqid(),
            'status' => PodcastStatus::Published,
            ...$overrides,
        ]);
    }

    private function episode(Podcast $podcast, array $overrides = []): PodcastEpisode
    {
        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Test Episode '.uniqid(),
            'slug' => 'test-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
            'publish_date' => now(),
            ...$overrides,
        ]);
    }
}
