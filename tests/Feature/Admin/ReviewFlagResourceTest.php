<?php

namespace Tests\Feature\Admin;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\ReviewFlag;
use App\Models\Role;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Admin Reviews" moderation queue (App\Filament\Resources\ReviewFlags\
 * ReviewFlagResource) — scoped to Review::flagged() only, a sibling to
 * Tests\Feature\Admin\ReviewResourceTest's "Light Posts & Comments" coverage.
 */
class ReviewFlagResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin_ui.show_community_menu' => true]);
    }

    public function test_only_flagged_reviews_appear_in_the_list(): void
    {
        $episode = $this->episode();
        $flagged = $this->comment($episode, User::factory()->create());
        $unflagged = $this->comment($episode, User::factory()->create());
        ReviewFlag::query()->create(['review_id' => $flagged->id, 'user_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->admin())->get('/admin/admin-reviews');

        $response->assertOk();
        $response->assertSee($flagged->content);
        $response->assertDontSee($unflagged->content);
    }

    public function test_dismissing_reports_clears_flags_without_touching_the_comment(): void
    {
        $episode = $this->episode();
        $review = $this->comment($episode, User::factory()->create());
        ReviewFlag::query()->create(['review_id' => $review->id, 'user_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->admin())->get("/admin/admin-reviews/{$review->id}");

        $response->assertOk();
        $this->assertSame(1, $review->flags()->count());
        $this->assertSame(ReviewStatus::Approved, $review->refresh()->status);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    private function comment(PodcastEpisode $episode, User $user): Review
    {
        return Review::query()->create([
            'reviewable_type' => PodcastEpisode::class,
            'reviewable_id' => $episode->id,
            'user_id' => $user->id,
            'rating' => null,
            'content' => 'A comment for the admin reviews queue '.uniqid().'.',
            'status' => ReviewStatus::Approved,
        ]);
    }

    private function episode(): PodcastEpisode
    {
        $podcast = Podcast::query()->create([
            'title' => 'Admin Reviews Test Podcast '.uniqid(),
            'slug' => 'admin-reviews-test-podcast-'.uniqid(),
            'status' => PodcastStatus::Published,
        ]);

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Admin Reviews Test Episode',
            'slug' => 'admin-reviews-test-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
        ]);
    }
}
