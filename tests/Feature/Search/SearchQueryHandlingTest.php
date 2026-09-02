<?php

namespace Tests\Feature\Search;

use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Models\Album;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Query-handling behavior for GET /search — empty/short/overlong/special-
 * character input, whitespace normalization, and pagination. Covers the
 * revised audit's §6 requirements directly; visibility/publication rules
 * are covered separately in SearchVisibilityTest.
 */
class SearchQueryHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_query_is_handled_safely_without_a_database_error(): void
    {
        $response = $this->get(route('search.index'));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => $results->getCollection()->isEmpty());
    }

    public function test_a_query_shorter_than_the_minimum_length_returns_no_results_rather_than_erroring(): void
    {
        $this->album(['title' => 'A', 'status' => ReleaseStatus::Published]);

        $response = $this->get(route('search.index', ['q' => 'a']));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => $results->getCollection()->isEmpty());
    }

    public function test_whitespace_only_query_is_treated_the_same_as_an_empty_query(): void
    {
        $response = $this->get(route('search.index', ['q' => '   ']));

        $response->assertOk();
        $response->assertViewHas('query', fn ($query) => $query === '');
    }

    /**
     * The normalization applies to the incoming query string (what a
     * visitor typed), not to stored content — a visitor typing extra/
     * leading/trailing whitespace around a real title should still match it
     * once SearchService::normalizeQuery() squishes their input down to a
     * single space between words.
     */
    public function test_extra_whitespace_in_the_typed_query_is_normalized_before_searching(): void
    {
        $album = $this->album(['title' => 'Golden Horizon '.uniqid(), 'status' => ReleaseStatus::Published]);

        $response = $this->get(route('search.index', ['q' => '  Golden    Horizon  ']));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => $results->getCollection()
            ->contains(fn ($result) => $result->url === route('music.albums.show', $album)));
    }

    public function test_an_overlong_query_is_clamped_rather_than_rejected(): void
    {
        $response = $this->get(route('search.index', ['q' => str_repeat('a', 500)]));

        $response->assertOk();
    }

    /**
     * "%" and "_" are LIKE wildcards — SearchService::results() escapes them
     * so a visitor's literal search for e.g. "50%" behaves as a literal
     * search rather than an unbounded wildcard match, and so a query
     * consisting only of these characters can't accidentally match
     * everything in the database.
     */
    public function test_percent_and_underscore_characters_are_handled_safely_as_literal_text(): void
    {
        $this->album(['title' => 'An Unrelated Album '.uniqid(), 'status' => ReleaseStatus::Published]);

        $response = $this->get(route('search.index', ['q' => '%_%']));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => $results->getCollection()->isEmpty());
    }

    public function test_special_characters_do_not_cause_a_server_error(): void
    {
        $response = $this->get(route('search.index', ['q' => '"OR 1=1; DROP TABLE users;-- \'']));

        $response->assertOk();
    }

    public function test_results_are_paginated(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->album(['title' => 'Paginated Result Album '.$i.' '.uniqid(), 'status' => ReleaseStatus::Published]);
        }

        $response = $this->get(route('search.index', ['q' => 'Paginated Result Album']));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => $results->count() === 12 && $results->total() === 15);

        $secondPage = $this->get(route('search.index', ['q' => 'Paginated Result Album', 'page' => 2]));
        $secondPage->assertOk();
        $secondPage->assertViewHas('results', fn ($results) => $results->count() === 3);
    }

    public function test_a_query_matching_nothing_shows_an_empty_state_without_erroring(): void
    {
        $response = $this->get(route('search.index', ['q' => 'a term nothing will ever match '.uniqid()]));

        $response->assertOk();
        $response->assertSee('No results match');
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
}
