<?php

namespace Tests\Feature;

use App\Models\LightPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage's "Latest Community Comments" strip — reuses that existing
 * display slot for real public Light Posts (see HomeController::
 * latestLightPosts()) rather than a parallel content mechanism. Previously
 * hardcoded fake quotes, unconditionally shown; now real data, hidden
 * entirely when there is none.
 */
class HomeLightPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_shows_a_public_light_posts_message(): void
    {
        $user = User::factory()->create(['name' => 'Grateful Guest']);
        LightPost::query()->create(['user_id' => $user->id, 'content' => 'Thankful for this community.', 'is_public' => true]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Grateful Guest');
        $response->assertSee('Thankful for this community.');
    }

    public function test_the_homepage_never_shows_a_non_public_light_post(): void
    {
        $user = User::factory()->create(['name' => 'Private Poster']);
        LightPost::query()->create(['user_id' => $user->id, 'content' => 'A private message.', 'is_public' => false]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('A private message.');
    }

    public function test_the_community_comments_section_is_absent_when_there_are_no_public_light_posts(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Latest Community Comments');
    }

    public function test_the_homepage_shows_at_most_four_light_posts(): void
    {
        foreach (range(1, 6) as $i) {
            $user = User::factory()->create(['name' => "Member {$i}"]);
            LightPost::query()->create(['user_id' => $user->id, 'content' => "Message number {$i}.", 'is_public' => true]);
        }

        $response = $this->get(route('home'));

        $response->assertOk();
        $shown = 0;
        foreach (range(1, 6) as $i) {
            if (str_contains($response->getContent(), "Message number {$i}.")) {
                $shown++;
            }
        }
        $this->assertSame(4, $shown);
    }
}
