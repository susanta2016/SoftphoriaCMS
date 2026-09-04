<?php

namespace Tests\Feature\Podcast;

use App\Enums\ReviewStatus;
use App\Models\Media;
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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Comments on a Podcast Episode, via the shared, reusable
 * App\Actions\Review\SubmitReviewAction and App\Models\Review (generic,
 * polymorphic — Music and Poetry/Prose reuse the exact same classes).
 * Publication behavior is driven entirely by
 * config('reviews.reviews_ratings_admin_approval'), never hardcoded.
 *
 * **Client-confirmed reversal (2026-09-02):** the public form no longer
 * collects a star rating — a submission is now a plain text comment, and
 * `rating` is always persisted as null for one. The separate 🙌 reaction is
 * covered by Tests\Feature\Podcast\ReactionSubmissionTest.
 */
class ReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Client-confirmed (2026-09-04): internal comments are disabled for
     * Podcast by default (config('features.podcast_comments_enabled')
     * defaults to false — discussion happens on YouTube instead, the 🙌
     * reaction is what stays on). Every test below except the dedicated
     * disabled-by-default ones re-enables the flag here so this suite
     * continues to exercise the full comment feature, independent of the
     * shipped production default.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['features.podcast_comments_enabled' => true]);
    }

    public function test_a_guest_cannot_submit_a_comment_and_is_redirected_to_login(): void
    {
        $episode = $this->episode();

        $response = $this->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'Guests should not be able to post this.',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_registered_user_can_submit_a_comment_without_a_rating(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'A genuinely thoughtful comment about this episode.',
        ]);

        $response->assertRedirect();
        $review = Review::query()->first();
        $this->assertNotNull($review);
        $this->assertSame($user->getKey(), $review->user_id);
        $this->assertSame($episode->getKey(), $review->reviewable_id);
        $this->assertSame(PodcastEpisode::class, $review->reviewable_type);
        $this->assertNull($review->rating);
    }

    public function test_a_rating_submitted_by_the_client_is_ignored(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'rating' => 5,
            'content' => 'A comment that also included a rating field.',
        ]);

        $review = Review::query()->first();
        $this->assertNotNull($review);
        $this->assertNull($review->rating);
    }

    public function test_review_content_is_required(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
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
            'content' => "   \n\t  ",
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_review_content_cannot_exceed_the_maximum_length(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => str_repeat('a', config('reviews.max_length') + 1),
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_the_maximum_length_is_configurable(): void
    {
        config(['reviews.max_length' => 10]);
        $episode = $this->episode();
        $user = User::factory()->create();

        $page = $this->actingAs($user)->get(route('podcast.episodes.show', $episode));
        $page->assertSee('maxlength="10"', false);

        $tooLong = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => str_repeat('a', 11),
        ]);
        $tooLong->assertSessionHasErrors('content');

        $withinLimit = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => str_repeat('a', 10),
        ]);
        $withinLimit->assertSessionDoesntHaveErrors('content');
        $this->assertSame(1, Review::query()->count());
    }

    public function test_a_review_is_pending_when_admin_approval_is_required(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'Pending comment content.',
        ]);

        $this->assertSame(ReviewStatus::Pending, Review::query()->first()->status);
    }

    /**
     * The pending comment is created directly (not via an authenticated HTTP
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
            'rating' => null,
            'content' => 'A distinctive pending comment sentinel.',
            'status' => ReviewStatus::Pending,
        ]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertDontSee('A distinctive pending comment sentinel');
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
            'content' => 'An immediately published comment.',
        ]);

        $this->assertSame(ReviewStatus::Approved, Review::query()->first()->status);

        $response = $this->get(route('podcast.episodes.show', $episode));
        $response->assertSee('An immediately published comment.');

        Mail::assertSent(TemplatedNotificationMail::class, fn ($mail): bool => $mail->hasTo($user->email));
    }

    /**
     * Client-confirmed reversal (2026-09-02): the old one-review-per-user
     * uniqueness made sense for a star rating, not for a comment feed — a
     * member can now leave any number of comments on the same item over
     * time. See database/migrations/2026_09_02_130000_drop_reviews_unique_constraint.php.
     */
    public function test_a_member_can_submit_multiple_comments_on_the_same_episode(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'First comment.',
        ]);
        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'Second, later comment.',
        ]);

        $this->assertSame(2, Review::query()->count());
        $this->assertSame(2, Review::query()->where('user_id', $user->id)->where('reviewable_id', $episode->id)->count());
    }

    public function test_the_first_comment_remains_intact_after_a_second_is_submitted(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'First comment.',
        ]);
        $first = Review::query()->first();

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'Second, later comment.',
        ]);

        $this->assertSame('First comment.', $first->refresh()->content);
    }

    public function test_each_comment_is_independently_moderated(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $episode = $this->episode();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'This one will be approved.',
        ]);
        $firstComment = Review::query()->first();
        $firstComment->update(['status' => ReviewStatus::Approved]);

        $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'This one stays pending.',
        ]);

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertSee('This one will be approved.');
        $response->assertDontSee('This one stays pending.');
    }

    public function test_the_comment_count_and_multiple_published_comments_display_correctly(): void
    {
        $episode = $this->episode();
        $this->createApprovedComment($episode, User::factory()->create(), 'Excellent episode.');
        $this->createApprovedComment($episode, User::factory()->create(), 'It was fine.');

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee('Excellent episode.');
        $response->assertSee('It was fine.');
        $response->assertSee('2 comments');
    }

    public function test_no_star_rating_language_or_widget_appears_on_the_page(): void
    {
        $episode = $this->episode();
        $this->createApprovedComment($episode, User::factory()->create(), 'A comment.');

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertDontSee('data-review-star', false);
        $response->assertDontSee('data-review-rating', false);
        $response->assertDontSee('Leave a Review');
        $response->assertDontSee('Submit Review');
        $response->assertDontSee('average', false);
    }

    public function test_the_empty_review_state_shows_no_fabricated_content(): void
    {
        $episode = $this->episode();

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee('Be the first to share your thoughts');
    }

    public function test_a_reviewer_with_no_avatar_shows_the_placeholder_image(): void
    {
        $episode = $this->episode();
        $this->createApprovedComment($episode, User::factory()->create(), 'No avatar set for this reviewer.');

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee(User::defaultAvatarUrl(), false);
    }

    public function test_a_reviewers_uploaded_avatar_is_shown(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/images/avatar.png', 'fake-avatar-bytes');
        $avatar = Media::query()->create([
            'disk' => 'public',
            'path' => 'media/images/avatar.png',
            'original_filename' => 'avatar.png',
            'mime_type' => 'image/png',
            'size' => 17,
            'visibility' => 'public',
        ]);
        $user = User::factory()->create();
        $user->profile()->create(['avatar_media_id' => $avatar->id]);

        $episode = $this->episode();
        $this->createApprovedComment($episode, $user, 'This reviewer has a real avatar.');

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url($avatar->path), false);
    }

    // --- Honeypot spam protection (reuses ContactController::store()'s exact pattern) ---

    public function test_a_legitimate_comment_submission_succeeds(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'A perfectly normal comment.',
            'hp_website' => '',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Review::query()->count());
    }

    public function test_a_honeypot_triggering_submission_is_silently_discarded(): void
    {
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'A bot filled the honeypot field.',
            'hp_website' => 'https://spam.example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('review_status');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_comments_are_disabled_when_the_module_config_is_off(): void
    {
        config(['features.podcast_comments_enabled' => false]);
        $episode = $this->episode();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('podcast.episodes.reviews.store', $episode), [
            'content' => 'This should never be persisted.',
        ]);

        $response->assertNotFound();
        $this->assertSame(0, Review::query()->count());
    }

    public function test_the_comment_ui_is_hidden_when_podcast_comments_are_disabled(): void
    {
        config(['features.podcast_comments_enabled' => false]);
        $episode = $this->episode();
        $this->createApprovedComment($episode, User::factory()->create(), 'A comment that must not render.');

        $response = $this->get(route('podcast.episodes.show', $episode));

        $response->assertOk();
        $response->assertDontSee('A comment that must not render.');
        $response->assertDontSee('data-review-form', false);
        $response->assertDontSee('Leave a Comment');
    }

    private function createApprovedComment(PodcastEpisode $episode, User $user, string $content): Review
    {
        return Review::query()->create([
            'reviewable_type' => PodcastEpisode::class,
            'reviewable_id' => $episode->id,
            'user_id' => $user->id,
            'rating' => null,
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
