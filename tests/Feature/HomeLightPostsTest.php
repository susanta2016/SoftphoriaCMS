<?php

namespace Tests\Feature;

use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage's "Latest Gratitude" carousel — reuses this existing display
 * slot for Public Gratitude Journal entries only (see HomeController::
 * latestGratitudeEntries()). Client-confirmed (2026-09-04): registration-time
 * Light Posts (source = registration) are deliberately excluded from this
 * section — they remain their own, separate, untouched feature — see
 * test_a_public_registration_light_post_does_not_appear_on_the_homepage()
 * below, the regression guard for that exact boundary.
 */
class HomeLightPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_shows_a_public_gratitude_journal_entry(): void
    {
        $user = User::factory()->create(['name' => 'Grateful Guest']);
        LightPost::query()->create([
            'user_id' => $user->id,
            'source' => LightPostSource::Journal,
            'content' => 'Thankful for this community.',
            'is_public' => true,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Grateful Guest');
        $response->assertSee('Thankful for this community.');
    }

    public function test_the_homepage_never_shows_a_private_gratitude_journal_entry(): void
    {
        $user = User::factory()->create(['name' => 'Private Poster']);
        LightPost::query()->create([
            'user_id' => $user->id,
            'source' => LightPostSource::Journal,
            'content' => 'A private message.',
            'is_public' => false,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('A private message.');
    }

    /**
     * The core of this round's requirement: a registration-time Light Post
     * (source = registration, the DB default — see the migration) must
     * never surface in this now-Gratitude-only carousel, even when public.
     * Registration Light Posts keep their own, separate existence (still
     * reachable via light-posts.show, still searchable) — they simply no
     * longer share this homepage display slot.
     */
    public function test_a_public_registration_light_post_does_not_appear_on_the_homepage(): void
    {
        $user = User::factory()->create(['name' => 'Registration Poster']);
        LightPost::query()->create([
            'user_id' => $user->id,
            'source' => LightPostSource::Registration,
            'content' => 'A registration-time light post message.',
            'is_public' => true,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('A registration-time light post message.');
        $response->assertDontSee('Latest Gratitude');
    }

    public function test_the_gratitude_section_is_absent_when_there_are_no_public_journal_entries(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Latest Gratitude');
    }

    public function test_the_homepage_shows_at_most_eight_gratitude_entries(): void
    {
        foreach (range(1, 9) as $i) {
            $user = User::factory()->create(['name' => "Member {$i}"]);
            LightPost::query()->create([
                'user_id' => $user->id,
                'source' => LightPostSource::Journal,
                'content' => "Message number {$i}.",
                'is_public' => true,
            ]);
        }

        $response = $this->get(route('home'));

        $response->assertOk();
        $shown = 0;
        foreach (range(1, 9) as $i) {
            if (str_contains($response->getContent(), "Message number {$i}.")) {
                $shown++;
            }
        }
        $this->assertSame(8, $shown);
    }
}
