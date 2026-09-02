<?php

namespace Tests\Feature\Community;

use App\Models\LightPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A single public Light Post's new minimal detail page
 * (LightPostController@show) — added only so a Light Post has a canonical
 * URL for unified Search to link to (see the revised audit's §4). Covers
 * exactly what that audit specified: only is_public=true posts are
 * reachable, private posts 404 for everyone (there is no "owner" concept
 * here — a Light Post is either public or it doesn't exist as a page),
 * and the page is noindex (per config/seo.php's own comment naming Light
 * Post explicitly) despite being genuinely public/reachable.
 */
class LightPostShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_light_post_is_viewable_by_a_guest(): void
    {
        $user = User::factory()->create(['name' => 'Casey Morgan']);
        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'content' => 'Grateful for this new morning.',
            'is_public' => true,
        ]);

        $response = $this->get(route('light-posts.show', $post));

        $response->assertOk();
        $response->assertSee('Grateful for this new morning.');
        $response->assertSee('Casey Morgan');
    }

    public function test_a_private_light_post_404s_for_a_guest(): void
    {
        $user = User::factory()->create();
        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'content' => 'A private thought.',
            'is_public' => false,
        ]);

        $response = $this->get(route('light-posts.show', $post));

        $response->assertNotFound();
    }

    public function test_a_private_light_post_404s_even_for_its_own_author(): void
    {
        $user = User::factory()->create();
        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'content' => 'A private thought only the author wrote.',
            'is_public' => false,
        ]);

        $response = $this->actingAs($user)->get(route('light-posts.show', $post));

        $response->assertNotFound();
    }

    public function test_a_nonexistent_light_post_404s(): void
    {
        $response = $this->get('/light-posts/does-not-exist');

        $response->assertNotFound();
    }

    public function test_the_public_light_post_page_is_marked_noindex(): void
    {
        $user = User::factory()->create();
        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'content' => 'A public reflection.',
            'is_public' => true,
        ]);

        $response = $this->get(route('light-posts.show', $post));

        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_the_public_light_post_page_has_a_canonical_url(): void
    {
        $user = User::factory()->create();
        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'content' => 'A public reflection with a canonical url.',
            'is_public' => true,
        ]);

        $response = $this->get(route('light-posts.show', $post));

        $response->assertSee('<link rel="canonical" href="'.route('light-posts.show', $post).'">', false);
    }

    public function test_the_light_post_page_is_never_present_in_the_sitemap(): void
    {
        $user = User::factory()->create();
        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'content' => 'A public reflection that stays out of the sitemap.',
            'is_public' => true,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('light-posts.show', $post), false);
    }
}
