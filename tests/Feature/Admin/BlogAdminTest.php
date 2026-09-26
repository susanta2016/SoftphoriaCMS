<?php

namespace Tests\Feature\Admin;

use App\Enums\BlogCommentStatus;
use App\Enums\BlogPostStatus;
use App\Filament\Pages\BlogSettings;
use App\Filament\Resources\BlogCategories\Pages\CreateBlogCategory;
use App\Filament\Resources\BlogComments\Pages\ListBlogComments;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogReactions\Pages\ListBlogReactions;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogReactionRecord;
use App\Models\IpLocation;
use App\Shared\Support\Blog\BlogSettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * Admin → Blog: Posts, Categories, Tags, Comments (report moderation),
 * Reactions and Blog Settings.
 */
class BlogAdminTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    public function test_non_admins_cannot_reach_the_blog_admin(): void
    {
        $member = $this->member();

        foreach (['posts', 'categories', 'tags', 'comments', 'reactions', 'settings'] as $screen) {
            $this->actingAs($member)->get("/admin/blog/{$screen}")->assertForbidden();
        }
    }

    public function test_every_blog_admin_screen_opens(): void
    {
        $admin = $this->admin();
        $post = $this->livePost();

        foreach (['/admin/blog/posts', '/admin/blog/posts/create', "/admin/blog/posts/{$post->getRouteKey()}/edit", '/admin/blog/categories', '/admin/blog/categories/create', '/admin/blog/tags', '/admin/blog/comments', '/admin/blog/reactions', '/admin/blog/settings'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_can_create_a_post_with_category_tags_and_seo(): void
    {
        $admin = $this->admin();
        $category = $this->category('Cloud');

        Livewire::actingAs($admin)
            ->test(CreateBlogPost::class)
            ->fillForm([
                'title' => 'Hello World',
                'slug' => 'hello-world',
                'excerpt' => 'Short.',
                'body' => '<p>Body text here.</p>',
                'status' => BlogPostStatus::Published->value,
                'published_at' => now()->subMinute()->toDateTimeString(),
                'blog_category_id' => $category->id,
                'seo.meta_title' => 'Custom meta title',
                'seo.meta_description' => 'Custom meta description.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = BlogPost::query()->sole();
        $this->assertSame('hello-world', $post->slug);
        $this->assertSame($category->id, $post->blog_category_id);
        $this->assertSame($admin->id, $post->created_by);
        $this->assertSame('Custom meta title', $post->seo->meta_title);
        $this->assertNull($post->seo->canonical_url, 'automatic canonical is stored as null');

        $this->get('/blog/hello-world')->assertOk()->assertSee('<title>Custom meta title</title>', false);
    }

    public function test_post_slugs_are_validated(): void
    {
        $this->livePost(['slug' => 'taken']);

        Livewire::actingAs($this->admin())
            ->test(CreateBlogPost::class)
            ->fillForm(['title' => 'X', 'slug' => 'taken', 'body' => '<p>x</p>', 'status' => 'draft'])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        Livewire::actingAs($this->admin())
            ->test(CreateBlogPost::class)
            ->fillForm(['title' => 'X', 'slug' => 'feed', 'body' => '<p>x</p>', 'status' => 'draft'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_editing_keeps_a_manual_canonical_override(): void
    {
        $post = $this->livePost(['slug' => 'orig']);

        Livewire::actingAs($this->admin())
            ->test(EditBlogPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['seo.canonical_url' => 'https://example.com/original-source'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('https://example.com/original-source', $post->fresh()->seo->canonical_url);
    }

    public function test_admin_can_create_a_category(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateBlogCategory::class)
            ->fillForm(['name' => 'Web Dev', 'slug' => 'web-dev', 'description' => 'All things web.', 'sort_order' => 1])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('All things web.', BlogCategory::query()->where('slug', 'web-dev')->sole()->description);
    }

    public function test_reported_comments_are_flagged_and_can_be_verified(): void
    {
        $admin = $this->admin();
        $comment = $this->reportedComment();

        Livewire::actingAs($admin)
            ->test(ListBlogComments::class)
            ->assertSet('activeTab', 'reported')
            ->assertCanSeeTableRecords([$comment])
            ->callTableAction('verify', $comment);

        $comment->refresh();
        $this->assertSame(BlogCommentStatus::Published, $comment->status);
        $this->assertSame(0, $comment->openReports()->count());
        $this->assertSame($admin->id, $comment->reviewed_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'blog_comment.verified']);
    }

    public function test_a_reported_comment_can_be_revoked_and_restored(): void
    {
        $admin = $this->admin();
        $comment = $this->reportedComment();

        Livewire::actingAs($admin)->test(ListBlogComments::class)->callTableAction('revoke', $comment);

        $comment->refresh();
        $this->assertSame(BlogCommentStatus::Revoked, $comment->status);
        $this->assertSame(0, $comment->openReports()->count());
        $this->get($comment->post->url())->assertDontSee('Buy cheap stuff');

        Livewire::actingAs($admin)
            ->test(ListBlogComments::class)
            ->set('activeTab', 'revoked')
            ->callTableAction('restore', $comment);

        $this->assertSame(BlogCommentStatus::Published, $comment->fresh()->status);
    }

    public function test_the_comments_list_shows_ip_and_location(): void
    {
        $comment = $this->reportedComment();
        $comment->update(['ip_address' => '49.37.10.20']);
        IpLocation::query()->create(['ip' => '49.37.10.20', 'city' => 'Kolkata', 'region' => 'West Bengal', 'country' => 'India', 'country_code' => 'IN', 'looked_up_at' => now()]);

        Livewire::actingAs($this->admin())
            ->test(ListBlogComments::class)
            ->assertSee('49.37.10.20')
            ->assertSee('Kolkata, West Bengal, India');
    }

    public function test_the_reactions_log_shows_member_emoji_and_location(): void
    {
        $post = $this->livePost(['title' => 'Reacted Post']);
        $reaction = BlogReactionRecord::query()->create(['blog_post_id' => $post->id, 'user_id' => $this->member('Priya')->id, 'reaction' => 'love', 'ip_address' => '1.1.1.1']);
        IpLocation::query()->create(['ip' => '1.1.1.1', 'city' => 'Brisbane', 'country' => 'Australia', 'country_code' => 'AU', 'looked_up_at' => now()]);

        Livewire::actingAs($this->admin())
            ->test(ListBlogReactions::class)
            ->assertCanSeeTableRecords([$reaction])
            ->assertSee('Priya')
            ->assertSee('Brisbane');
    }

    public function test_blog_settings_save(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BlogSettings::class)
            ->fillForm(['title' => 'Engineering Blog', 'per_page' => 12, 'card_style' => 'bordered', 'cta_heading' => 'Hire us'])
            ->call('save');

        $settings = app(BlogSettingsRepository::class);
        $this->assertSame('Engineering Blog', $settings->get('title'));
        $this->assertSame(12, $settings->get('per_page'));
        $this->assertSame('bordered', $settings->get('card_style'));

        $this->livePost(['slug' => 'p']);
        $this->get('/blog')->assertSee('Engineering Blog');
        $this->get('/blog/p')->assertSee('Hire us');
    }

    private function reportedComment(): BlogComment
    {
        $post = $this->livePost();
        $comment = $post->comments()->create(['user_id' => $this->member('Spammer')->id, 'body' => 'Buy cheap stuff', 'status' => BlogCommentStatus::Published]);
        $comment->reports()->create(['user_id' => $this->member('Reporter')->id, 'reason' => 'spam']);

        return $comment;
    }
}
