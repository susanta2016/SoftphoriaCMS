<?php

namespace Tests\Feature\Public;

use App\Enums\BlogPostStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Models\BlogComment;
use App\Models\BlogCommentReport;
use App\Models\BlogPost;
use App\Models\BlogReactionRecord;
use App\Models\User;
use App\Shared\Support\Features\Features;
use Database\Seeders\HomePageSeeder;
use Database\Seeders\NavigationMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CreatesBlogContent;
use Tests\Support\PassesFormTimeTrap;
use Tests\TestCase;

/**
 * BLOG-003: commenting (posting and deleting) needs an Active account with a
 * verified email (EnsureAccountCanComment) — no admin bypass — while
 * reactions and reports keep their existing rules; plus the placeholder
 * post / test-interaction cleanup migration.
 */
class BlogCommentVerificationTest extends TestCase
{
    use CreatesBlogContent;
    use PassesFormTimeTrap;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['ipinfo.io/*' => Http::response(['country' => 'IN'])]);
    }

    public function test_an_active_verified_member_can_comment_and_delete_their_comment(): void
    {
        $post = $this->livePost();
        $member = $this->member();

        $this->actingAs($member)->post(route('blog.comments.store', $post), $this->comment('Great article!'))->assertRedirect();
        $comment = BlogComment::query()->sole();

        $this->actingAs($member)->delete(route('blog.comments.destroy', $comment))->assertRedirect();
        $this->assertSame(0, BlogComment::query()->count());
    }

    public function test_a_pending_verification_member_cannot_comment(): void
    {
        $post = $this->livePost();
        $pending = $this->pendingMember();

        $this->actingAs($pending)->from($post->url())
            ->post(route('blog.comments.store', $post), $this->comment('Let me in'))
            ->assertRedirect($post->url())
            ->assertSessionHasErrors(['body' => 'Verify your email to comment.'], null, 'comment');

        $this->actingAs($pending)->postJson(route('blog.comments.store', $post), $this->comment('Let me in'))->assertForbidden();
        $this->assertSame(0, BlogComment::query()->count());
        // Still signed in — they can keep reading.
        $this->assertAuthenticatedAs($pending);
    }

    public function test_a_pending_verification_member_cannot_delete_their_own_comment(): void
    {
        $post = $this->livePost();
        $member = $this->member();
        $comment = BlogComment::query()->create(['blog_post_id' => $post->id, 'user_id' => $member->id, 'body' => 'Before my email changed.']);
        // Changing email puts an account back to PendingVerification.
        $member->forceFill(['status' => UserStatus::PendingVerification->value, 'email_verified_at' => null])->save();

        $this->actingAs($member)->delete(route('blog.comments.destroy', $comment))->assertRedirect();
        $this->assertModelExists($comment);
    }

    public function test_an_active_but_unverified_member_cannot_comment(): void
    {
        $post = $this->livePost();
        $unverified = User::factory()->unverified()->create(['status' => UserStatus::Active->value]);

        $this->actingAs($unverified)->post(route('blog.comments.store', $post), $this->comment('Hello'))->assertSessionHasErrors(['body'], null, 'comment');
        $this->assertSame(0, BlogComment::query()->count());
    }

    public function test_an_active_verified_admin_can_comment(): void
    {
        $post = $this->livePost();

        $this->actingAs($this->admin())->post(route('blog.comments.store', $post), $this->comment('Author here.'))->assertRedirect();
        $this->assertSame(1, BlogComment::query()->count());
    }

    public function test_an_active_but_unverified_admin_cannot_comment(): void
    {
        $post = $this->livePost();
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($admin)->post(route('blog.comments.store', $post), $this->comment('Author here.'))->assertSessionHasErrors(['body'], null, 'comment');
        $this->assertSame(0, BlogComment::query()->count());
    }

    public function test_a_pending_member_sees_the_verification_notice_and_resend_action_instead_of_the_form(): void
    {
        $post = $this->livePost();
        $pending = $this->pendingMember();

        $html = $this->actingAs($pending)->get($post->url())->assertOk()->getContent();

        $this->assertStringContainsString('Verify your email to comment.', $html);
        $this->assertStringContainsString('action="'.route('verification.resend').'"', $html);
        $this->assertStringContainsString('name="email" value="'.$pending->email.'"', $html);
        $this->assertStringNotContainsString('data-comment-form', $html);
        $this->assertStringNotContainsString('Join the conversation', $html);
    }

    public function test_the_resend_action_is_the_existing_verification_mechanism(): void
    {
        $pending = $this->pendingMember();

        $this->actingAs($pending)
            ->from('/blog')
            ->post(route('verification.resend'), ['email' => $pending->email])
            ->assertRedirect('/blog')
            ->assertSessionHas('status');
    }

    public function test_an_active_unverified_member_sees_the_notice_without_a_resend_button(): void
    {
        $post = $this->livePost();
        $unverified = User::factory()->unverified()->create(['status' => UserStatus::Active->value]);

        $html = $this->actingAs($unverified)->get($post->url())->getContent();

        $this->assertStringContainsString('Verify your email to comment.', $html);
        $this->assertStringNotContainsString(route('verification.resend'), $html);
        $this->assertStringNotContainsString('data-comment-form', $html);
    }

    public function test_verified_members_see_the_form_and_guests_see_the_login_prompt(): void
    {
        $post = $this->livePost();

        $this->actingAs($this->member())->get($post->url())->assertSee('data-comment-form', false)->assertDontSee('Verify your email to comment.');
        auth()->logout();

        $this->get($post->url())
            ->assertSee('Join the conversation')
            ->assertSee(route('blog.join', $post), false)
            ->assertDontSee('data-comment-form', false)
            ->assertDontSee('Verify your email to comment.');
        $this->post(route('blog.comments.store', $post), $this->comment('Hi'))->assertRedirect(route('login'));
    }

    public function test_blocked_statuses_remain_blocked_from_commenting(): void
    {
        $post = $this->livePost();

        foreach ([UserStatus::Suspended, UserStatus::Locked, UserStatus::Banned] as $status) {
            $user = $this->member();
            $user->forceFill(['status' => $status->value])->save();

            $this->actingAs($user)->post(route('blog.comments.store', $post), $this->comment('Hi'))->assertRedirect(route('login'));
            $this->assertGuest();
        }

        $this->assertSame(0, BlogComment::query()->count());
    }

    public function test_reactions_and_reports_keep_their_existing_rules_for_pending_members(): void
    {
        $post = $this->livePost();
        $pending = $this->pendingMember();
        $comment = BlogComment::query()->create(['blog_post_id' => $post->id, 'user_id' => $this->member('Other')->id, 'body' => 'A comment.']);
        app(Features::class)->set('blog.comment_reports', true);

        $this->actingAs($pending)->postJson(route('blog.reactions.toggle', $post), ['reaction' => 'like'])->assertOk();
        $this->actingAs($pending)->post(route('blog.comments.report', $comment), ['reason' => 'spam'])->assertRedirect();

        $this->assertSame(1, BlogReactionRecord::query()->count());
        $this->assertSame(1, BlogCommentReport::query()->count());
    }

    public function test_the_cleanup_migration_drafts_the_placeholders_and_removes_only_the_test_interactions(): void
    {
        $demo = User::factory()->create(['email' => 'demo.member@example.com']);
        $demo2 = User::factory()->create(['email' => 'demo.member2@example.com']);
        $real = $this->member('Real Reader');
        $admin = $this->admin();

        $aws = $this->livePost(['slug' => 'aws-migration-guide', 'title' => 'A Practical Guide to Migrating Applications to AWS']);
        $django = $this->livePost(['slug' => 'django-vs-flask', 'title' => 'Django vs Flask: Which is Right for Your Project?']);
        $this->livePost(['slug' => 'scalable-apis-with-laravel', 'title' => 'Building Scalable APIs with Laravel']);
        $this->livePost(['slug' => 'lessons-from-client-projects', 'title' => 'Lessons from 20 Years of Client Projects']);
        $retitled = $this->livePost(['slug' => 'lessons-from-client-projects-2', 'title' => 'An Approved Article']);

        $testComment = BlogComment::query()->create(['blog_post_id' => $aws->id, 'user_id' => $demo->id, 'body' => 'Really useful breakdown — the point about DNS TTLs bit us last year. Would love a follow-up on cost monitoring.']);
        BlogComment::query()->create(['blog_post_id' => $aws->id, 'user_id' => $demo2->id, 'body' => 'Check out my site for cheap hosting!!! best deals']);
        BlogCommentReport::query()->create(['blog_comment_id' => $testComment->id, 'user_id' => $demo2->id, 'reason' => 'spam']);
        $realComment = BlogComment::query()->create(['blog_post_id' => $aws->id, 'user_id' => $real->id, 'body' => 'A genuine comment.']);

        BlogReactionRecord::query()->create(['blog_post_id' => $aws->id, 'user_id' => $demo->id, 'reaction' => 'like']);
        BlogReactionRecord::query()->create(['blog_post_id' => $aws->id, 'user_id' => $demo2->id, 'reaction' => 'insightful']);
        $testReaction = BlogReactionRecord::query()->create(['blog_post_id' => $django->id, 'user_id' => $admin->id, 'reaction' => 'love']);
        DB::table('blog_reactions')->where('id', $testReaction->id)->update(['created_at' => '2026-09-26 09:02:34']);
        $realReaction = BlogReactionRecord::query()->create(['blog_post_id' => $aws->id, 'user_id' => $real->id, 'reaction' => 'like']);

        (require database_path('migrations/2026_09_28_120000_unpublish_placeholder_blog_posts.php'))->up();

        $this->assertSame(4, BlogPost::query()->where('status', BlogPostStatus::Draft)->count());
        $this->assertSame(BlogPostStatus::Published, $retitled->fresh()->status);
        $this->assertSame([$realComment->id], BlogComment::query()->pluck('id')->all());
        $this->assertSame([$realReaction->id], BlogReactionRecord::query()->pluck('id')->all());
        $this->assertSame(0, BlogCommentReport::query()->count());
        $this->assertSame(4, BlogPost::query()->whereIn('slug', ['aws-migration-guide', 'django-vs-flask', 'scalable-apis-with-laravel', 'lessons-from-client-projects'])->count());
    }

    public function test_drafted_posts_leave_the_blog_homepage_and_sitemap_but_stay_in_admin(): void
    {
        $this->admin();
        $this->seed(NavigationMenuSeeder::class);
        $this->seed(HomePageSeeder::class);
        $post = $this->livePost(['title' => 'A Practical Guide to Migrating Applications to AWS', 'slug' => 'aws-migration-guide']);
        $post->update(['status' => BlogPostStatus::Draft]);

        $this->get('/blog')->assertOk()->assertDontSee('A Practical Guide to Migrating Applications to AWS');
        $this->get('/')->assertOk()->assertDontSee('A Practical Guide to Migrating Applications to AWS')->assertDontSee('id="insights"', false);
        $this->get('/sitemap.xml')->assertDontSee('aws-migration-guide');
        $this->get('/blog/aws-migration-guide')->assertNotFound();

        Livewire::actingAs($this->admin())->test(ListBlogPosts::class)->assertCanSeeTableRecords([$post]);
    }

    private function pendingMember(): User
    {
        return User::factory()->unverified()->create(['status' => UserStatus::PendingVerification->value]);
    }

    /**
     * @return array<string, string>
     */
    private function comment(string $body): array
    {
        return ['body' => $body, '_started' => $this->formStartedToken()];
    }
}
