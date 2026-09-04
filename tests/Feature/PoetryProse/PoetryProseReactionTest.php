<?php

namespace Tests\Feature\PoetryProse;

use App\Models\Reaction;
use App\Models\Review;
use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 🙌 reaction on a Poetry/Prose entry — fully independent of
 * Tests\Feature\PoetryProse\PoetryProseReviewTest's comment coverage
 * (client-confirmed, 2026-09-02: a member reacts, comments, does both, or
 * does neither). App\Models\Reaction is a separate table/model from
 * App\Models\Review — never a repurposed star rating.
 */
class PoetryProseReactionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Client-confirmed (2026-09-04): reactions are disabled for Poetry/Prose
     * by default (config('features.poetry_prose_reactions_enabled') defaults
     * to false). Every test below except the dedicated disabled-by-default
     * ones re-enables the flag here so this suite continues to exercise the
     * full reaction feature, independent of the shipped production default.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['features.poetry_prose_reactions_enabled' => true]);
    }

    public function test_a_guest_cannot_react_and_is_redirected_to_login(): void
    {
        $entry = $this->entry();

        $response = $this->post(route('poetry-prose.reactions.toggle', $entry));

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_an_authenticated_user_can_react(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('poetry-prose.reactions.toggle', $entry));

        $response->assertRedirect();
        $this->assertSame(1, Reaction::query()->count());
        $reaction = Reaction::query()->first();
        $this->assertSame($user->getKey(), $reaction->user_id);
        $this->assertSame($entry->getKey(), $reaction->reactable_id);
        $this->assertSame(PoetryProse::class, $reaction->reactable_type);
    }

    // --- Async (fetch) endpoint — client-confirmed reversal, 2026-09-02:
    // the 🙌 tap is now a JS fetch, not a full page reload. postJson()
    // sends Accept: application/json, exactly like resources/js/app.js's
    // real fetch() call, so the controller takes its $request->wantsJson()
    // branch and returns JSON instead of a redirect. ---

    public function test_the_async_endpoint_returns_an_appropriate_json_response(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('poetry-prose.reactions.toggle', $entry));

        $response->assertOk();
        $response->assertJson(['reacted' => true, 'count' => 1]);
    }

    public function test_the_async_endpoint_reports_reacted_false_and_a_lower_count_after_toggling_off(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('poetry-prose.reactions.toggle', $entry));
        $response = $this->actingAs($user)->postJson(route('poetry-prose.reactions.toggle', $entry));

        $response->assertOk();
        $response->assertJson(['reacted' => false, 'count' => 0]);
    }

    /**
     * bootstrap/app.php scopes JSON exception rendering to `api/*` paths
     * only (`shouldRenderJsonWhen`), site-wide — this route isn't under
     * `/api`, so even a request with Accept: application/json still gets
     * the normal HTML redirect-to-login for an unauthenticated request,
     * same as the plain-POST guest test above. Documented here rather than
     * changing that global exception config for this one endpoint — the
     * frontend fetch's own catch-all (resources/js/app.js) already falls
     * back to a real form submit on anything that isn't a clean JSON
     * response, so the guest still lands on the login page either way.
     */
    public function test_a_guest_requesting_json_still_gets_redirected_to_login(): void
    {
        $entry = $this->entry();

        $response = $this->postJson(route('poetry-prose.reactions.toggle', $entry));

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_an_unpublished_entry_returns_404_from_the_async_endpoint(): void
    {
        $entry = $this->entry(['status' => PoetryProseStatus::Draft]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('poetry-prose.reactions.toggle', $entry));

        $response->assertStatus(404);
    }

    public function test_the_reaction_count_increments_on_the_public_page(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reactions.toggle', $entry));

        $response = $this->get(route('poetry-prose.show', $entry));
        $response->assertOk();
        $response->assertSee('🙌');
        $response->assertSee('1');
    }

    public function test_toggling_again_removes_the_reaction_and_the_count_decreases(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reactions.toggle', $entry));
        $this->assertSame(1, Reaction::query()->count());

        $this->actingAs($user)->post(route('poetry-prose.reactions.toggle', $entry));
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_the_same_user_cannot_create_a_duplicate_reaction(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        Reaction::query()->create([
            'reactable_type' => PoetryProse::class,
            'reactable_id' => $entry->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(QueryException::class);

        Reaction::query()->create([
            'reactable_type' => PoetryProse::class,
            'reactable_id' => $entry->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_two_different_users_can_each_react_to_the_same_entry(): void
    {
        $entry = $this->entry();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)->post(route('poetry-prose.reactions.toggle', $entry));
        $this->actingAs($userB)->post(route('poetry-prose.reactions.toggle', $entry));

        $this->assertSame(2, Reaction::query()->count());
    }

    public function test_a_user_can_react_without_leaving_a_comment(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reactions.toggle', $entry));

        $this->assertSame(1, Reaction::query()->count());
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_user_can_comment_without_reacting(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'A comment with no reaction.',
        ]);

        $this->assertSame(1, Review::query()->count());
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_a_user_can_do_both_independently(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'A comment alongside a reaction.',
        ]);
        $this->actingAs($user)->post(route('poetry-prose.reactions.toggle', $entry));

        $this->assertSame(1, Review::query()->count());
        $this->assertSame(1, Reaction::query()->count());
    }

    public function test_an_unpublished_entry_cannot_receive_a_reaction(): void
    {
        $entry = $this->entry(['status' => PoetryProseStatus::Draft]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('poetry-prose.reactions.toggle', $entry));

        $response->assertNotFound();
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_reacting_to_one_entry_never_affects_another_entrys_count(): void
    {
        $entryA = $this->entry(['title' => 'Entry A', 'slug' => 'reaction-entry-a']);
        $entryB = $this->entry(['title' => 'Entry B', 'slug' => 'reaction-entry-b']);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reactions.toggle', $entryA));

        $this->assertSame(1, $entryA->reactions()->count());
        $this->assertSame(0, $entryB->reactions()->count());
    }

    public function test_reactions_are_disabled_when_the_module_config_is_off(): void
    {
        config(['features.poetry_prose_reactions_enabled' => false]);
        $entry = $this->entry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('poetry-prose.reactions.toggle', $entry));

        $response->assertNotFound();
        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_the_reaction_button_is_hidden_when_poetry_prose_reactions_are_disabled(): void
    {
        config(['features.poetry_prose_reactions_enabled' => false]);
        $entry = $this->entry();

        $response = $this->actingAs(User::factory()->create())->get(route('poetry-prose.show', $entry));

        $response->assertOk();
        $response->assertDontSee('data-reaction-form', false);
        $response->assertDontSee('data-reaction-button', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function entry(array $overrides = []): PoetryProse
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
