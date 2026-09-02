<?php

namespace Tests\Feature\Search;

use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Models\Album;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /search/suggest — the header autocomplete's own endpoint. Covers the
 * revised audit's §2/§7 requirements: JSON shape, minimum length enforced
 * server-side (never rely on client-side debounce/length alone), a limited
 * suggestion count, and rate limiting.
 */
class SearchSuggestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_short_query_returns_an_empty_suggestion_list_rather_than_an_error(): void
    {
        $response = $this->getJson('/search/suggest?q=a');

        $response->assertOk();
        $response->assertJson(['suggestions' => []]);
    }

    public function test_an_empty_query_returns_an_empty_suggestion_list(): void
    {
        $response = $this->getJson('/search/suggest');

        $response->assertOk();
        $response->assertJson(['suggestions' => []]);
    }

    public function test_a_matching_query_returns_a_suggestion_with_the_expected_shape(): void
    {
        $album = $this->album(['title' => 'Suggestible Horizon '.uniqid(), 'status' => ReleaseStatus::Published]);

        $response = $this->getJson('/search/suggest?q=Suggestible+Horizon');

        $response->assertOk();
        $response->assertJsonFragment([
            'type' => 'Music',
            'title' => $album->title,
            'url' => route('music.albums.show', $album),
        ]);
        $response->assertJsonStructure(['query', 'suggestions', 'viewAllUrl']);
    }

    public function test_suggestions_are_limited_to_a_small_number(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->album(['title' => 'Capped Result '.$i.' '.uniqid(), 'status' => ReleaseStatus::Published]);
        }

        $response = $this->getJson('/search/suggest?q=Capped+Result');

        $response->assertOk();
        $this->assertLessThanOrEqual(6, count($response->json('suggestions')));
    }

    public function test_a_suggestion_never_includes_unpublished_content(): void
    {
        $entry = $this->poetryProse(['title' => 'Hidden Suggestion Entry '.uniqid(), 'status' => PoetryProseStatus::Draft]);

        $response = $this->getJson('/search/suggest?q=Hidden+Suggestion+Entry');

        $response->assertOk();
        $response->assertJson(['suggestions' => []]);
    }

    public function test_the_suggest_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/search/suggest?q=test')->assertOk();
        }

        $this->getJson('/search/suggest?q=test')->assertStatus(429);
    }

    private function album(array $overrides = []): Album
    {
        return Album::query()->create([
            'title' => 'An Album',
            'slug' => 'an-album-'.uniqid(),
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }

    private function poetryProse(array $overrides = []): PoetryProse
    {
        return PoetryProse::query()->create([
            'title' => 'A Poetry/Prose Entry',
            'slug' => 'a-poetry-prose-entry-'.uniqid(),
            'body' => '<p>Body content.</p>',
            'content_type' => PoetryProseContentType::Essay,
            'status' => PoetryProseStatus::Published,
            'publish_at' => now(),
            ...$overrides,
        ]);
    }
}
