<?php

namespace Tests\Feature\PoetryProse;

use App\Models\Category;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public Poetry/Prose — fully public once Published (client-confirmed: no
 * membership/entitlement gate on viewing), noindex only when an admin
 * explicitly sets it via SeoFields::indexing(), and sitemap inclusion
 * mirroring Page's own rules exactly. Also covers the listing page's
 * search/category/type/sort/pagination controls and the detail page's
 * previous/next navigation added for the frontend redesign — mirrors
 * PodcastControllerTest's shape (nothing hardcoded, every assertion reads
 * back a value the test itself put in the database).
 */
class PoetryProseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_page_lists_published_entries(): void
    {
        $published = $this->entry(['title' => 'Published Piece', 'status' => PoetryProseStatus::Published]);
        $draft = $this->entry(['title' => 'Draft Piece', 'slug' => 'draft-piece', 'status' => PoetryProseStatus::Draft]);

        $response = $this->get(route('poetry-prose.index'));

        $response->assertOk();
        $response->assertSee('Published Piece');
        $response->assertDontSee('Draft Piece');
    }

    public function test_the_index_page_shows_an_empty_state_when_there_are_no_entries(): void
    {
        $response = $this->get(route('poetry-prose.index'));

        $response->assertOk();
        $response->assertSee('No entries to show yet');
    }

    public function test_the_index_page_search_filters_by_title(): void
    {
        $match = $this->entry(['title' => 'Zebra Migration Reflections '.uniqid(), 'slug' => 'zebra-'.uniqid()]);
        $other = $this->entry(['title' => 'Unrelated Topic '.uniqid(), 'slug' => 'unrelated-'.uniqid()]);

        $response = $this->get(route('poetry-prose.index', ['q' => 'Zebra Migration']));

        $response->assertOk();
        $response->assertSee($match->title);
        // The sidebar's "Popular Reads" widget is intentionally unfiltered
        // (same as Podcast's own sidebar pattern), so it may legitimately
        // still show $other — the assertion belongs on the actual filtered
        // result set, not a whole-page text scan.
        $response->assertViewHas('entries', fn ($entries) => $entries->pluck('id')->contains($match->id)
            && ! $entries->pluck('id')->contains($other->id));
    }

    public function test_the_index_page_filters_by_category(): void
    {
        $category = Category::query()->create(['type' => 'poetry_prose', 'name' => 'Reflections', 'slug' => 'reflections-'.uniqid()]);
        $other = Category::query()->create(['type' => 'poetry_prose', 'name' => 'Poems', 'slug' => 'poems-'.uniqid()]);

        $match = $this->entry(['title' => 'Categorized Piece '.uniqid(), 'slug' => 'categorized-'.uniqid()]);
        $match->categories()->attach($category);

        $excluded = $this->entry(['title' => 'Other Category Piece '.uniqid(), 'slug' => 'other-cat-'.uniqid()]);
        $excluded->categories()->attach($other);

        $response = $this->get(route('poetry-prose.index', ['category' => $category->slug]));

        $response->assertOk();
        $response->assertSee($match->title);
        $response->assertViewHas('entries', fn ($entries) => $entries->pluck('id')->contains($match->id)
            && ! $entries->pluck('id')->contains($excluded->id));
    }

    public function test_the_index_page_filters_by_content_type(): void
    {
        $poem = $this->entry(['title' => 'A Poem Entry '.uniqid(), 'slug' => 'poem-'.uniqid(), 'content_type' => PoetryProseContentType::Poetry]);
        $essay = $this->entry(['title' => 'An Essay Entry '.uniqid(), 'slug' => 'essay-'.uniqid(), 'content_type' => PoetryProseContentType::Essay]);

        $response = $this->get(route('poetry-prose.index', ['content_type' => PoetryProseContentType::Poetry->value]));

        $response->assertOk();
        $response->assertSee($poem->title);
        $response->assertViewHas('entries', fn ($entries) => $entries->pluck('id')->contains($poem->id)
            && ! $entries->pluck('id')->contains($essay->id));
    }

    public function test_the_index_page_sorts_oldest_first_when_requested(): void
    {
        $older = $this->entry(['title' => 'Older Entry '.uniqid(), 'slug' => 'older-'.uniqid(), 'publish_at' => now()->subDays(5)]);
        $newer = $this->entry(['title' => 'Newer Entry '.uniqid(), 'slug' => 'newer-'.uniqid(), 'publish_at' => now()]);

        $response = $this->get(route('poetry-prose.index', ['sort' => 'oldest']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, $newer->title), strpos($content, $older->title));
    }

    public function test_an_ajax_request_returns_only_the_results_partial(): void
    {
        $entry = $this->entry(['title' => 'Ajax Partial Entry '.uniqid(), 'slug' => 'ajax-partial-'.uniqid()]);

        $response = $this->get(route('poetry-prose.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertSee($entry->title);
        // The partial has no <html>/<head> — only a real full-page request
        // renders the layout shell.
        $response->assertDontSee('<!DOCTYPE html>', false);
    }

    public function test_the_index_page_paginates_results(): void
    {
        $entries = [];
        for ($i = 0; $i < 8; $i++) {
            $entries[] = $this->entry(['title' => 'Paginated Entry '.$i.' '.uniqid(), 'slug' => 'paginated-'.$i.'-'.uniqid(), 'publish_at' => now()->subMinutes($i)]);
        }

        // Newest-first default: entries 0-5 (the six most recent) land on
        // page one, entries 6-7 are pushed to page two.
        $response = $this->get(route('poetry-prose.index'));

        $response->assertOk();
        $response->assertSee($entries[0]->title);
        $response->assertDontSee($entries[7]->title);
        $response->assertViewHas('entries', fn ($paginator) => $paginator->total() === 8 && $paginator->hasPages());

        $secondPage = $this->get(route('poetry-prose.index', ['page' => 2]));
        $secondPage->assertOk();
        $secondPage->assertSee($entries[7]->title);
    }

    public function test_a_published_entry_is_publicly_viewable_without_authentication(): void
    {
        $entry = $this->entry(['status' => PoetryProseStatus::Published]);

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertOk();
        $response->assertSee($entry->title);
    }

    public function test_the_detail_page_does_not_hardcode_article_content(): void
    {
        $entry = $this->entry([
            'title' => 'A Very Distinctive Sentinel Title '.uniqid(),
            'body' => '<p>A distinctive sentinel paragraph '.uniqid().'.</p>',
        ]);

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertOk();
        $response->assertSee($entry->title);
        $response->assertSee(strip_tags($entry->body));
        $response->assertDontSee('The Quiet Within');
    }

    public function test_a_draft_entry_404s_publicly(): void
    {
        $entry = $this->entry(['status' => PoetryProseStatus::Draft]);

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertNotFound();
    }

    public function test_an_archived_entry_404s_publicly(): void
    {
        $entry = $this->entry(['status' => PoetryProseStatus::Archived]);

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertNotFound();
    }

    public function test_a_published_entry_is_never_marked_noindex_by_default(): void
    {
        $entry = $this->entry(['status' => PoetryProseStatus::Published]);

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertDontSee('noindex', false);
    }

    public function test_the_detail_page_links_to_the_previous_and_next_entries_in_publish_order(): void
    {
        $earliest = $this->entry(['title' => 'Earliest Entry '.uniqid(), 'slug' => 'earliest-'.uniqid(), 'publish_at' => now()->subDays(2)]);
        $middle = $this->entry(['title' => 'Middle Entry '.uniqid(), 'slug' => 'middle-'.uniqid(), 'publish_at' => now()->subDay()]);
        $latest = $this->entry(['title' => 'Latest Entry '.uniqid(), 'slug' => 'latest-'.uniqid(), 'publish_at' => now()]);

        $response = $this->get(route('poetry-prose.show', $middle));

        $response->assertOk();
        $response->assertViewHas('previous', fn ($previous) => $previous?->is($earliest));
        $response->assertViewHas('next', fn ($next) => $next?->is($latest));
    }

    public function test_the_detail_page_handles_no_previous_or_next_entry_gracefully(): void
    {
        $only = $this->entry(['status' => PoetryProseStatus::Published]);

        $response = $this->get(route('poetry-prose.show', $only));

        $response->assertOk();
        $response->assertViewHas('previous', fn ($previous) => $previous === null);
        $response->assertViewHas('next', fn ($next) => $next === null);
    }

    public function test_the_detail_page_renders_without_a_featured_image_category_or_excerpt(): void
    {
        $entry = $this->entry([
            'title' => 'Bare Minimum Entry '.uniqid(),
            'featured_image_id' => null,
        ]);

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertOk();
        $response->assertSee($entry->title);
    }

    public function test_sitemap_includes_a_published_entry(): void
    {
        $entry = $this->entry(['status' => PoetryProseStatus::Published]);

        $response = $this->get('/sitemap.xml');

        $response->assertSee(route('poetry-prose.show', $entry), false);
    }

    public function test_sitemap_excludes_a_draft_entry(): void
    {
        $entry = $this->entry(['status' => PoetryProseStatus::Draft]);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('poetry-prose.show', $entry), false);
    }

    public function test_sitemap_excludes_a_published_entry_explicitly_marked_noindex(): void
    {
        $entry = $this->entry(['status' => PoetryProseStatus::Published]);
        $entry->seo()->create(['robots' => 'noindex, nofollow']);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('poetry-prose.show', $entry), false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function entry(array $overrides = []): PoetryProse
    {
        return PoetryProse::query()->create([
            'title' => 'A Poetry/Prose Entry',
            'slug' => 'a-poetry-prose-entry',
            'body' => '<p>Body content.</p>',
            'content_type' => PoetryProseContentType::Essay,
            'status' => PoetryProseStatus::Published,
            'publish_at' => now(),
            ...$overrides,
        ]);
    }
}
