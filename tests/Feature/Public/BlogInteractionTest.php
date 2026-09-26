<?php

namespace Tests\Feature\Public;

use App\Enums\BlogCommentStatus;
use App\Models\BlogComment;
use App\Models\BlogReactionRecord;
use App\Models\IpLocation;
use App\Shared\Support\Features\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesBlogContent;
use Tests\Support\PassesFormTimeTrap;
use Tests\TestCase;

/**
 * Member comments, reports (red flags) and emoji reactions on the blog,
 * including IP capture + geolocation.
 */
class BlogInteractionTest extends TestCase
{
    use CreatesBlogContent;
    use PassesFormTimeTrap;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['ipinfo.io/*' => Http::response([
            'city' => 'Kolkata', 'region' => 'West Bengal', 'country' => 'IN', 'loc' => '22.5726,88.3639',
            'org' => 'AS9829 BSNL', 'postal' => '700001', 'timezone' => 'Asia/Kolkata',
        ])]);
    }

    public function test_guests_cannot_comment(): void
    {
        $post = $this->livePost();

        $this->post(route('blog.comments.store', $post), ['body' => 'Hi'])->assertRedirect(route('login'));
        $this->assertSame(0, BlogComment::query()->count());
    }

    public function test_a_member_comment_publishes_instantly_and_records_the_ip_location(): void
    {
        $post = $this->livePost();
        $member = $this->member('Priya Sharma');

        $this->actingAs($member)
            ->withServerVariables(['REMOTE_ADDR' => '49.37.10.20'])
            ->post(route('blog.comments.store', $post), ['body' => 'Great article!', '_started' => $this->formStartedToken()])
            ->assertRedirect();

        $comment = BlogComment::query()->sole();
        $this->assertSame(BlogCommentStatus::Published, $comment->status);
        $this->assertSame('49.37.10.20', $comment->ip_address);

        $location = IpLocation::query()->where('ip', '49.37.10.20')->sole();
        $this->assertSame('Kolkata', $location->city);
        $this->assertSame('India', $location->country);
        $this->assertSame($location->id, $comment->ipLocation->id);

        $this->get($post->url())->assertSee('Great article!')->assertSee('Priya Sharma');
    }

    public function test_admins_can_join_the_discussion_too(): void
    {
        $post = $this->livePost();

        $this->actingAs($this->admin())
            ->post(route('blog.comments.store', $post), ['body' => 'Author here.', '_started' => $this->formStartedToken()])
            ->assertRedirect();

        $this->assertSame(1, BlogComment::query()->count());
    }

    public function test_honeypot_and_too_fast_comments_are_discarded(): void
    {
        $post = $this->livePost();
        $member = $this->member();

        $this->actingAs($member)->post(route('blog.comments.store', $post), ['body' => 'spam', 'hp_website' => 'x', '_started' => $this->formStartedToken()]);
        $this->actingAs($member)->post(route('blog.comments.store', $post), ['body' => 'spam', '_started' => $this->formStartedToken(0)]);

        $this->assertSame(0, BlogComment::query()->count());
    }

    public function test_comment_validation(): void
    {
        $post = $this->livePost();

        $this->actingAs($this->member())
            ->post(route('blog.comments.store', $post), ['body' => 'see http://a.x http://b.x http://c.x', '_started' => $this->formStartedToken()])
            ->assertSessionHasErrorsIn('comment', 'body');
    }

    public function test_comments_respect_the_feature_switch_and_the_post_setting(): void
    {
        $post = $this->livePost(['allow_comments' => false]);
        $member = $this->member();

        $this->actingAs($member)->post(route('blog.comments.store', $post), ['body' => 'Hi', '_started' => $this->formStartedToken()])->assertNotFound();
        $this->get($post->url())->assertDontSee('Discussion');

        $post->update(['allow_comments' => true]);
        app(Features::class)->set('blog.comments', false);

        $this->actingAs($member)->post(route('blog.comments.store', $post), ['body' => 'Hi', '_started' => $this->formStartedToken()])->assertNotFound();
    }

    public function test_members_can_delete_only_their_own_comments(): void
    {
        $post = $this->livePost();
        $author = $this->member();
        $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Mine', 'status' => BlogCommentStatus::Published]);

        $this->actingAs($this->member('Other'))->delete(route('blog.comments.destroy', $comment))->assertForbidden();
        $this->actingAs($author)->delete(route('blog.comments.destroy', $comment))->assertRedirect();

        $this->assertModelMissing($comment);
    }

    public function test_members_can_report_someone_elses_comment_once(): void
    {
        $post = $this->livePost();
        $author = $this->member('Author');
        $reporter = $this->member('Reporter');
        $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Buy cheap stuff', 'status' => BlogCommentStatus::Published]);

        $this->actingAs($reporter)->postJson(route('blog.comments.report', $comment), ['reason' => 'spam'])->assertOk();
        $this->actingAs($reporter)->postJson(route('blog.comments.report', $comment), ['reason' => 'abusive', 'details' => 'Rude'])->assertOk();

        $this->assertSame(1, $comment->reports()->count());
        $this->assertSame('abusive', $comment->reports()->sole()->reason->value);

        $this->actingAs($author)->postJson(route('blog.comments.report', $comment), ['reason' => 'spam'])->assertStatus(422);
        $this->actingAs($reporter)->postJson(route('blog.comments.report', $comment), ['reason' => 'nonsense'])->assertStatus(422);
    }

    public function test_the_report_button_follows_its_feature_switch(): void
    {
        $post = $this->livePost();
        $post->comments()->create(['user_id' => $this->member('Author')->id, 'body' => 'Hello', 'status' => BlogCommentStatus::Published]);
        $viewer = $this->member('Viewer');

        $this->actingAs($viewer)->get($post->url())->assertSee('Report this comment');

        app(Features::class)->set('blog.comment_reports', false);

        $this->actingAs($viewer)->get($post->url())->assertDontSee('Report this comment');
    }

    public function test_revoked_comments_are_hidden_from_the_site(): void
    {
        $post = $this->livePost();
        $post->comments()->create(['user_id' => $this->member()->id, 'body' => 'Hidden text', 'status' => BlogCommentStatus::Revoked]);

        $this->get($post->url())->assertDontSee('Hidden text');
    }

    public function test_reactions_toggle_and_record_the_ip(): void
    {
        $post = $this->livePost();
        $member = $this->member();

        $this->actingAs($member)
            ->withServerVariables(['REMOTE_ADDR' => '49.37.10.20'])
            ->postJson(route('blog.reactions.toggle', $post), ['reaction' => 'fire'])
            ->assertOk()
            ->assertJson(['counts' => ['fire' => 1], 'mine' => ['fire']]);

        $reaction = BlogReactionRecord::query()->sole();
        $this->assertSame('49.37.10.20', $reaction->ip_address);
        $this->assertSame('Kolkata', $reaction->ipLocation->city);

        $this->actingAs($member)
            ->postJson(route('blog.reactions.toggle', $post), ['reaction' => 'fire'])
            ->assertJson(['mine' => []]);

        $this->assertSame(0, BlogReactionRecord::query()->count());
    }

    public function test_reactions_need_a_member_a_valid_emoji_and_the_feature(): void
    {
        $post = $this->livePost();

        // Guests are sent to log in (the UI shows them a link, not a button).
        $this->postJson(route('blog.reactions.toggle', $post), ['reaction' => 'like'])->assertRedirect(route('login'));
        $this->actingAs($this->member())->postJson(route('blog.reactions.toggle', $post), ['reaction' => 'poop'])->assertStatus(422);

        app(Features::class)->set('blog.reactions', false);
        $this->actingAs($this->member('Other'))->postJson(route('blog.reactions.toggle', $post), ['reaction' => 'like'])->assertNotFound();
        $this->get($post->url())->assertDontSee('Was this helpful?');
    }

    public function test_blocked_accounts_cannot_interact(): void
    {
        $post = $this->livePost();
        $banned = $this->member();
        $banned->forceFill(['status' => 'banned'])->save();

        $this->actingAs($banned)->postJson(route('blog.reactions.toggle', $post), ['reaction' => 'like'])->assertForbidden();
        $this->assertSame(0, BlogReactionRecord::query()->count());
    }
}
