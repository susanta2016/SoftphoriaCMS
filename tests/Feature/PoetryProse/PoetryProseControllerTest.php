<?php

namespace Tests\Feature\PoetryProse;

use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public Poetry/Prose — fully public once Published (client-confirmed: no
 * membership/entitlement gate on viewing), noindex only when an admin
 * explicitly sets it via SeoFields::indexing(), and sitemap inclusion
 * mirroring Page's own rules exactly.
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

    public function test_a_published_entry_is_publicly_viewable_without_authentication(): void
    {
        $entry = $this->entry(['status' => PoetryProseStatus::Published]);

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertOk();
        $response->assertSee($entry->title);
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
            'status' => PoetryProseStatus::Draft,
            ...$overrides,
        ]);
    }
}
