<?php

namespace Tests\Feature\PoetryProse;

use App\Enums\ReviewStatus;
use App\Models\Media;
use App\Models\Review;
use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Comments on a Poetry/Prose entry — via the exact same shared
 * App\Models\Review / App\Actions\Review\SubmitReviewAction that Podcast
 * and Music already use (see Tests\Feature\Music\TrackReviewTest, the
 * reference this test mirrors). No module-specific review logic exists;
 * this test exists only to confirm PoetryProse's Reviewable wiring
 * (reviews(), reviewTitle(), reviewUrl()) and its own controller/route.
 *
 * **Client-confirmed reversal (2026-09-02):** the public form no longer
 * collects a star rating — a submission is now a plain text comment, and
 * `rating` is always persisted as null for one. The separate 🙌 reaction is
 * covered by Tests\Feature\PoetryProse\PoetryProseReactionTest.
 */
class PoetryProseReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_submit_a_comment_and_is_redirected_to_login(): void
    {
        $entry = $this->entry();

        $response = $this->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'Guests should not be able to post this.',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_registered_user_can_submit_a_comment_without_a_rating(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $entry = $this->entry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'A genuinely thoughtful comment about this piece.',
        ]);

        $response->assertRedirect();
        $review = Review::query()->first();
        $this->assertNotNull($review);
        $this->assertSame($user->getKey(), $review->user_id);
        $this->assertSame($entry->getKey(), $review->reviewable_id);
        $this->assertSame(PoetryProse::class, $review->reviewable_type);
        $this->assertNull($review->rating);
    }

    public function test_a_rating_submitted_by_the_client_is_ignored(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'rating' => 5,
            'content' => 'A comment that also included a rating field.',
        ]);

        $review = Review::query()->first();
        $this->assertNotNull($review);
        $this->assertNull($review->rating);
    }

    public function test_review_content_is_required(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => '',
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_whitespace_only_review_content_is_rejected(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => "   \n\t  ",
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_review_content_cannot_exceed_the_maximum_length(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => str_repeat('a', config('reviews.max_length') + 1),
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_review_is_pending_when_admin_approval_is_required(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'Pending comment content.',
        ]);

        $this->assertSame(ReviewStatus::Pending, Review::query()->first()->status);
    }

    public function test_a_pending_review_is_not_publicly_visible(): void
    {
        $entry = $this->entry();
        Review::query()->create([
            'reviewable_type' => PoetryProse::class,
            'reviewable_id' => $entry->id,
            'user_id' => User::factory()->create()->id,
            'rating' => null,
            'content' => 'A distinctive pending comment sentinel.',
            'status' => ReviewStatus::Pending,
        ]);

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertDontSee('A distinctive pending comment sentinel');
        $response->assertSee('Be the first to share your thoughts');
    }

    public function test_an_approved_review_becomes_publicly_visible(): void
    {
        $entry = $this->entry();
        $this->createApprovedComment($entry, User::factory()->create(), 'A wonderful, approved comment.');

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertOk();
        $response->assertSee('A wonderful, approved comment.');
    }

    public function test_admin_approval_disabled_publishes_immediately_and_sends_the_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        config(['reviews.reviews_ratings_admin_approval' => false]);
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'An immediately published comment.',
        ]);

        $this->assertSame(ReviewStatus::Approved, Review::query()->first()->status);

        $response = $this->get($entry->reviewUrl());
        $response->assertSee('An immediately published comment.');
        Mail::assertSent(TemplatedNotificationMail::class, fn ($mail): bool => $mail->hasTo($user->email));
    }

    /**
     * Client-confirmed reversal (2026-09-02): the old one-review-per-user
     * uniqueness made sense for a star rating, not for a comment feed — a
     * member can now leave any number of comments on the same item over
     * time. See database/migrations/2026_09_02_130000_drop_reviews_unique_constraint.php.
     */
    public function test_a_member_can_submit_multiple_comments_on_the_same_entry(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'First comment.',
        ]);
        $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'Second, later comment.',
        ]);

        $this->assertSame(2, Review::query()->count());
        $this->assertSame(2, Review::query()->where('user_id', $user->id)->where('reviewable_id', $entry->id)->count());
    }

    public function test_the_first_comment_remains_intact_after_a_second_is_submitted(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'First comment.',
        ]);
        $first = Review::query()->first();

        $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'Second, later comment.',
        ]);

        $this->assertSame('First comment.', $first->refresh()->content);
    }

    public function test_each_comment_is_independently_moderated(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $entry = $this->entry();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'This one will be approved.',
        ]);
        $firstComment = Review::query()->first();
        $firstComment->update(['status' => ReviewStatus::Approved]);

        $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'This one stays pending.',
        ]);

        $response = $this->get($entry->reviewUrl());

        $response->assertSee('This one will be approved.');
        $response->assertDontSee('This one stays pending.');
    }

    public function test_reviews_for_one_entry_never_appear_on_another_entrys_page(): void
    {
        $entryA = $this->entry(['title' => 'Entry A', 'slug' => 'entry-a']);
        $entryB = $this->entry(['title' => 'Entry B', 'slug' => 'entry-b']);

        $this->createApprovedComment($entryA, User::factory()->create(), 'A comment that only belongs to Entry A.');

        $responseA = $this->get(route('poetry-prose.show', $entryA));
        $responseA->assertSee('A comment that only belongs to Entry A.');

        $responseB = $this->get(route('poetry-prose.show', $entryB));
        $responseB->assertDontSee('A comment that only belongs to Entry A.');
        $responseB->assertSee('Be the first to share your thoughts');
    }

    public function test_the_comment_count_uses_only_approved_reviews(): void
    {
        $entry = $this->entry();
        $this->createApprovedComment($entry, User::factory()->create(), 'Excellent piece.');
        $this->createApprovedComment($entry, User::factory()->create(), 'It was fine.');
        Review::query()->create([
            'reviewable_type' => PoetryProse::class,
            'reviewable_id' => $entry->id,
            'user_id' => User::factory()->create()->id,
            'rating' => null,
            'content' => 'A pending comment that must not count yet.',
            'status' => ReviewStatus::Pending,
        ]);

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertOk();
        $response->assertSee('2 comments');
        $response->assertDontSee('A pending comment that must not count yet.');
    }

    public function test_no_star_rating_language_or_widget_appears_on_the_page(): void
    {
        $entry = $this->entry();
        $this->createApprovedComment($entry, User::factory()->create(), 'A comment.');

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertOk();
        $response->assertDontSee('data-review-star', false);
        $response->assertDontSee('data-review-rating', false);
        $response->assertDontSee('Leave a Review');
        $response->assertDontSee('Submit Review');
        $response->assertDontSee('average', false);
    }

    public function test_the_reviews_section_appears_on_the_entry_page(): void
    {
        $entry = $this->entry();

        $response = $this->get(route('poetry-prose.show', $entry));

        $response->assertOk();
        $response->assertSee('What Readers Are Saying');
    }

    public function test_the_empty_review_state_shows_no_fabricated_content(): void
    {
        $entry = $this->entry();

        $response = $this->get($entry->reviewUrl());

        $response->assertOk();
        $response->assertSee('Be the first to share your thoughts');
    }

    public function test_a_guest_sees_a_login_prompt_instead_of_the_submission_form(): void
    {
        $entry = $this->entry();

        $response = $this->get($entry->reviewUrl());

        $response->assertOk();
        $response->assertSee('to leave a comment');
        $response->assertDontSee('data-review-form', false);
    }

    public function test_a_reviewer_with_no_avatar_shows_the_placeholder_image(): void
    {
        $entry = $this->entry();
        $this->createApprovedComment($entry, User::factory()->create(), 'No avatar set for this reviewer.');

        $response = $this->get($entry->reviewUrl());

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

        $entry = $this->entry();
        $this->createApprovedComment($entry, $user, 'This reviewer has a real avatar.');

        $response = $this->get($entry->reviewUrl());

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url($avatar->path), false);
    }

    // --- Honeypot spam protection (reuses ContactController::store()'s exact pattern) ---

    public function test_a_legitimate_comment_submission_succeeds(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'A perfectly normal comment.',
            'hp_website' => '',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Review::query()->count());
    }

    public function test_a_honeypot_triggering_submission_is_silently_discarded(): void
    {
        $entry = $this->entry();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('poetry-prose.reviews.store', $entry), [
            'content' => 'A bot filled the honeypot field.',
            'hp_website' => 'https://spam.example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('review_status');
        $this->assertSame(0, Review::query()->count());
    }

    private function createApprovedComment(PoetryProse $entry, User $user, string $content): Review
    {
        return Review::query()->create([
            'reviewable_type' => PoetryProse::class,
            'reviewable_id' => $entry->id,
            'user_id' => $user->id,
            'rating' => null,
            'content' => $content,
            'status' => ReviewStatus::Approved,
        ]);
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
