<?php

namespace Tests\Feature\Public;

use App\Enums\BlogPostStatus;
use App\Models\BlogTag;
use App\Shared\Support\Blog\BlogSettingsRepository;
use Database\Seeders\HomePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * The public blog: landing, archives, search, async fragments, single
 * posts, SEO output, RSS, sitemap and the homepage Latest Blog Posts
 * section.
 */
class BlogPublicTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    public function test_the_landing_page_lists_only_live_posts(): void
    {
        $this->livePost(['title' => 'Live Post']);
        $this->livePost(['title' => 'Draft Post', 'status' => BlogPostStatus::Draft]);
        $this->livePost(['title' => 'Scheduled Post', 'published_at' => now()->addDay()]);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Live Post')
            ->assertDontSee('Draft Post')
            ->assertDontSee('Scheduled Post')
            ->assertSee('rel="alternate" type="application/rss+xml"', false)
            ->assertSee('"@type":"CollectionPage"', false);
    }

    public function test_the_featured_post_leads_the_first_page_only(): void
    {
        $this->livePost(['title' => 'Star Post', 'is_featured' => true]);

        $this->get('/blog')->assertSee('Featured')->assertSee('Star Post');
    }

    public function test_draft_and_scheduled_posts_404_for_the_public_but_preview_for_admins(): void
    {
        $draft = $this->livePost(['slug' => 'secret', 'status' => BlogPostStatus::Draft]);

        $this->get('/blog/secret')->assertNotFound();
        $this->actingAs($this->member())->get('/blog/secret')->assertNotFound();

        $this->actingAs($this->admin())
            ->get('/blog/secret')
            ->assertOk()
            ->assertSee('Preview')
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_a_post_renders_toc_seo_and_breadcrumbs(): void
    {
        $category = $this->category('Cloud');
        $post = $this->livePost(['title' => 'Migrating to AWS', 'slug' => 'aws', 'blog_category_id' => $category->id, 'author_id' => $this->admin()->id]);
        $post->tags()->attach(BlogTag::query()->create(['name' => 'AWS', 'slug' => 'aws']));

        $this->get('/blog/aws')
            ->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('id="first-section"', false)
            ->assertSee('href="#first-section"', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"articleSection":"Cloud"', false)
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('<link rel="canonical" href="'.url('/blog/aws').'"', false)
            ->assertSee('/blog/category/cloud', false)
            ->assertSee('#AWS');
    }

    public function test_category_archives_are_indexable_and_tag_archives_are_not(): void
    {
        $category = $this->category('Cloud');
        $post = $this->livePost(['title' => 'In Cloud', 'blog_category_id' => $category->id]);
        $this->livePost(['title' => 'Elsewhere']);
        $tag = BlogTag::query()->create(['name' => 'Laravel', 'slug' => 'laravel']);
        $post->tags()->attach($tag);

        $this->get('/blog/category/cloud')
            ->assertOk()
            ->assertSee('In Cloud')
            ->assertDontSee('Elsewhere')
            ->assertSee('All about Cloud.')
            ->assertSee('name="robots" content="index, follow"', false);

        $this->get('/blog/tag/laravel')
            ->assertOk()
            ->assertSee('In Cloud')
            ->assertSee('name="robots" content="noindex, follow"', false);
    }

    public function test_search_filters_and_is_noindexed(): void
    {
        $this->livePost(['title' => 'Kubernetes basics']);
        $this->livePost(['title' => 'Laravel queues']);

        $this->get('/blog?q=kube')
            ->assertOk()
            ->assertSee('Kubernetes basics')
            ->assertDontSee('Laravel queues')
            ->assertSee('noindex, follow', false);
    }

    public function test_async_requests_get_only_the_listing_fragment(): void
    {
        $this->livePost(['title' => 'Fragment Post']);

        $response = $this->withHeader('X-Requested-With', 'XMLHttpRequest')->get('/blog');

        $response->assertOk()->assertSee('data-async-region="blog"', false)->assertSee('Fragment Post')->assertDontSee('<html', false);
        $this->assertStringContainsString('X-Requested-With', $response->headers->get('Vary'));
    }

    public function test_pagination_uses_the_blog_settings_page_size_and_self_canonical(): void
    {
        app(BlogSettingsRepository::class)->save(['per_page' => 3, 'show_featured' => false]);

        foreach (range(1, 5) as $i) {
            $this->livePost(['title' => "Paged {$i}", 'published_at' => now()->subDays($i)]);
        }

        $this->get('/blog')->assertSee('Paged 3')->assertDontSee('Paged 4')->assertSee('rel="next"', false);
        $this->get('/blog?page=2')->assertSee('Paged 4')->assertSee('<link rel="canonical" href="'.url('/blog').'?page=2"', false);
    }

    public function test_the_rss_feed_lists_live_posts(): void
    {
        $this->livePost(['title' => 'Feed Post']);

        $response = $this->get('/blog/feed')->assertOk()->assertSee('<rss', false)->assertSee('Feed Post');
        $this->assertStringContainsString('application/rss+xml', $response->headers->get('Content-Type'));
    }

    public function test_the_sitemap_includes_live_posts_and_categories_but_not_noindex_posts(): void
    {
        $category = $this->category('Cloud');
        $this->livePost(['slug' => 'indexed', 'blog_category_id' => $category->id]);
        $hidden = $this->livePost(['slug' => 'hidden']);
        $hidden->seo()->create(['robots' => 'noindex, follow']);
        $this->livePost(['slug' => 'draft', 'status' => BlogPostStatus::Draft]);

        $this->get('/sitemap.xml')
            ->assertSee(url('/blog'), false)
            ->assertSee(url('/blog/indexed'), false)
            ->assertSee(url('/blog/category/cloud'), false)
            ->assertDontSee(url('/blog/hidden'), false)
            ->assertDontSee(url('/blog/draft'), false);
    }

    public function test_the_homepage_latest_insights_section_shows_the_newest_posts(): void
    {
        $this->admin();
        $this->seed(HomePageSeeder::class);

        $this->get('/')->assertDontSee('Ideas, tutorials and technology.');

        foreach (range(1, 4) as $i) {
            $this->livePost(['title' => "Insight {$i}", 'published_at' => now()->subDays($i)]);
        }

        $this->get('/')
            ->assertSee('Ideas, tutorials and technology.')
            ->assertSeeInOrder(['Insight 1', 'Insight 2', 'Insight 3'])
            ->assertDontSee('Insight 4');
    }

    public function test_guests_see_reaction_counts_and_a_login_prompt(): void
    {
        $this->livePost(['slug' => 'p']);

        $this->get('/blog/p')
            ->assertSee('Join the conversation')
            ->assertSee(route('blog.join', 'p'), false)
            ->assertDontSee('name="body"', false);

        $this->get('/blog/p/join')->assertRedirect(route('login'));
    }
}
