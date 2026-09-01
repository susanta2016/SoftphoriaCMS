<?php

namespace Tests\Feature\Podcast;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Reviews/ratings on a Podcast Episode, via the shared, reusable
 * App\Actions\Review\SubmitReviewAction and App\Models\Review (generic,
 * polymorphic — Music/Inspirational Resources are meant to reuse the exact
 * same classes later). Publication behavior is driven entirely by
 * config('reviews.reviews_ratings_admin_approval'), never hardcoded.
 */
class ReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_submit_a_review_and_is_redirected_to_login(): void
    {
        $episode = $this->episode();

        $response = $this->post(route('podcast.episodes.reviews.store', $episode), [
            'rating' => 5,
            'content' => 'Guests should not be able to post this.',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_registered_user_can_submit_a_review(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'rating' => 4,
            'content' => 'A genuinely thoughtful review of this episode.',
        ]);

        $response->assertRedirect();
        $review = Review::query()->first();
        $this->assertNotNull($review);
        $this->assertSame($user->getKey(), $review->user_id);
        $this->assertSame($episode->getKey(), $review->reviewable_id);
        $this->assertSame(PodcastEpisode::class, $review->reviewable_type);
        $this->assertSame(4, $review->rating);
    }

    public function test_rating_must_be_between_1_and_5(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'rating' => 6,
            'content' => 'Valid content but an invalid rating.',
        ]);

        $response->assertSessionHasErrors('rating');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_review_content_is_required(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'rating' => 5,
            'content' => '',
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_whitespace_only_review_content_is_rejected(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'rating' => 5,
            'content' => "   \n\t  ",
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_rating_is_required(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'Valid content but a missing rating.',
        ]);

        $response->assertSessionHasErrors('rating');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_review_content_cannot_exceed_the_maximum_length(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'rating' => 5,
            'content' => str_repeat('a', 301),
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_review_is_pending_when_admin_approval_is_required(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'rating' => 5,
            'content' => 'Pending review content.',
        ]);

        $this->assertSame(ReviewStatus::Pending, Review::query()->first()->status);
    }

    /**
     * The pending review is created directly (not via an authenticated HTTP
     * submission first) so this assertion is checked from a genuine, freshly
     * unauthenticated guest request — the submitter's own edit form
     * pre-filling their own pending content when they view the page
     * themselves is separate, intended behavior, not a public leak.
     */
    public function test_a_pending_review_is_not_publicly_visible(): void
    {
        $episode = $this->episode();
        Review::query()->create([
            'reviewable_type' => PodcastEpisode::class,
            'reviewable_id' => $episode->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 5,
            'content' => 'A distinctive pending review sentinel.',
            'status' => ReviewStatus::Pending,
        ]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertDontSee('A distinctive pending review sentinel');
        $response->assertSee('Be the first to share your thoughts');
    }

    public function test_admin_approval_disabled_publishes_immediately_and_sends_the_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        config(['reviews.reviews_ratings_admin_approval' => false]);
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'rating' => 5,
            'content' => 'An immediately published review.',
        ]);

        $this->assertSame(ReviewStatus::Approved, Review::query()->first()->status);

        $response = $this->get(route('podcast.episodes.show', $episode));
        $response->assertSee('An immediately published review.');

        Mail::assertSent(TemplatedNotificationMail::class, fn ($mail): bool => $mail->hasTo($user->email));
    }

    public function test_resubmitting_updates_the_existing_review_rather_than_creating_a_duplicate(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'rating' => 3,
            'content' => 'First submission.',
        ]);
        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'rating' => 5,
            'content' => 'Updated submission.',
        ]);

        $this->assertSame(1, Review::query()->count());
        $review = Review::query()->first();
        $this->assertSame(5, $review->rating);
        $this->assertSame('Updated submission.', $review->content);
    }

    public function test_the_rating_summary_and_multiple_published_reviews_display_correctly(): void
    {
        $episode = $this->episode();
        $this->createApprovedReview($episode, User::factory()->create(), 5, 'Excellent episode.');
        $this->createApprovedReview($episode, User::factory()->create(), 3, 'It was fine.');

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee('Excellent episode.');
        $response->assertSee('It was fine.');
        $response->assertSee('4 average'); // (5+3)/2 rounded to one decimal renders as 4
        $response->assertSee('2 reviews');
    }

    public function test_the_empty_review_state_shows_no_fabricated_content(): void
    {
        $episode = $this->episode();

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee('Be the first to share your thoughts');
    }

    private function createApprovedReview(PodcastEpisode $episode, User $user, int $rating, string $content): Review
    {
        return Review::query()->create([
            'reviewable_type' => PodcastEpisode::class,
            'reviewable_id' => $episode->id,
            'user_id' => $user->id,
            'rating' => $rating,
            'content' => $content,
            'status' => ReviewStatus::Approved,
        ]);
    }

    private function episode(): PodcastEpisode
    {
        $podcast = Podcast::query()->create([
            'title' => 'Review Test Podcast '.uniqid(),
            'slug' => 'review-test-podcast-'.uniqid(),
            'status' => PodcastStatus::Published,
        ]);

        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Review Test Episode',
            'slug' => 'review-test-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
        ]);
    }
}
