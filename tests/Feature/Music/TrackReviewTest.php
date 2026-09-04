<?php

namespace Tests\Feature\Music;

use App\Enums\ReviewStatus;
use App\Models\Media;
use App\Models\Review;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Comments on a Music Track (a Single's own song, or an Album-owned track)
 * — via the exact same shared App\Models\Review /
 * App\Actions\Review\SubmitReviewAction the Podcast module already uses
 * (see Tests\Feature\Podcast\ReviewSubmissionTest, the reference this test
 * mirrors). Reviews belong to the individual Track, never the Album as a
 * whole — a Single's "track" is its one underlying Track row (Single::
 * track()), so both entry points are exercised here.
 *
 * **Client-confirmed reversal (2026-09-02):** the public form no longer
 * collects a star rating — a submission is now a plain text comment, and
 * `rating` is always persisted as null for one. The separate 🙌 reaction
 * is covered by Tests\Feature\Music\TrackReactionTest.
 */
class TrackReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Client-confirmed (2026-09-04): internal comments are disabled for
     * Music by default (config('features.music_comments_enabled') defaults
     * to false, the 🙌 reaction is what stays on). Every test below except
     * the dedicated disabled-by-default ones re-enables the flag here so
     * this suite continues to exercise the full comment feature, independent
     * of the shipped production default.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['features.music_comments_enabled' => true]);
    }

    public function test_a_guest_cannot_submit_a_comment_and_is_redirected_to_login(): void
    {
        $track = $this->publishedSingleTrack();

        $response = $this->post(route('music.tracks.reviews.store', $track), [
            'content' => 'Guests should not be able to post this.',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_registered_user_can_submit_a_comment_without_a_rating(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'A genuinely thoughtful comment about this song.',
        ]);

        $response->assertRedirect();
        $review = Review::query()->first();
        $this->assertNotNull($review);
        $this->assertSame($user->getKey(), $review->user_id);
        $this->assertSame($track->getKey(), $review->reviewable_id);
        $this->assertSame(Track::class, $review->reviewable_type);
        $this->assertNull($review->rating);
    }

    public function test_a_rating_submitted_by_the_client_is_ignored(): void
    {
        // The public form no longer renders a rating field at all, but even
        // if a client (or a stale cached page) posts one, it must never be
        // persisted — the comment-only contract is enforced server-side.
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'rating' => 5,
            'content' => 'A comment that also included a rating field.',
        ]);

        $review = Review::query()->first();
        $this->assertNotNull($review);
        $this->assertNull($review->rating);
    }

    public function test_review_content_is_required(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => '',
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_whitespace_only_review_content_is_rejected(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => "   \n\t  ",
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_review_content_cannot_exceed_the_maximum_length(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => str_repeat('a', config('reviews.max_length') + 1),
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_the_maximum_length_is_configurable(): void
    {
        config(['reviews.max_length' => 10]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $page = $this->actingAs($user)->get($track->reviewUrl());
        $page->assertSee('maxlength="10"', false);

        $tooLong = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => str_repeat('a', 11),
        ]);
        $tooLong->assertSessionHasErrors('content');

        $withinLimit = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => str_repeat('a', 10),
        ]);
        $withinLimit->assertSessionDoesntHaveErrors('content');
        $this->assertSame(1, Review::query()->count());
    }

    public function test_a_review_is_pending_when_admin_approval_is_required(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'Pending comment content.',
        ]);

        $this->assertSame(ReviewStatus::Pending, Review::query()->first()->status);
    }

    public function test_a_pending_review_is_not_publicly_visible(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);
        Review::query()->create([
            'reviewable_type' => Track::class,
            'reviewable_id' => $track->id,
            'user_id' => User::factory()->create()->id,
            'rating' => null,
            'content' => 'A distinctive pending comment sentinel.',
            'status' => ReviewStatus::Pending,
        ]);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertDontSee('A distinctive pending comment sentinel');
        $response->assertSee('Be the first to share your thoughts');
    }

    public function test_an_approved_review_becomes_publicly_visible(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);
        $this->createApprovedComment($track, User::factory()->create(), 'A wonderful, approved comment.');

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertSee('A wonderful, approved comment.');
    }

    public function test_admin_approval_disabled_publishes_immediately_and_sends_the_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        config(['reviews.reviews_ratings_admin_approval' => false]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'An immediately published comment.',
        ]);

        $this->assertSame(ReviewStatus::Approved, Review::query()->first()->status);

        $response = $this->get($track->reviewUrl());
        $response->assertSee('An immediately published comment.');
        Mail::assertSent(TemplatedNotificationMail::class, fn ($mail): bool => $mail->hasTo($user->email));
    }

    /**
     * Client-confirmed reversal (2026-09-02): the old one-review-per-user
     * uniqueness made sense for a star rating, not for a comment feed — a
     * member can now leave any number of comments on the same item over
     * time. See database/migrations/2026_09_02_130000_drop_reviews_unique_constraint.php.
     */
    public function test_a_member_can_submit_multiple_comments_on_the_same_track(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'First comment.',
        ]);
        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'Second, later comment.',
        ]);

        $this->assertSame(2, Review::query()->count());
        $this->assertSame(2, Review::query()->where('user_id', $user->id)->where('reviewable_id', $track->id)->count());
    }

    public function test_the_first_comment_remains_intact_after_a_second_is_submitted(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'First comment.',
        ]);
        $first = Review::query()->first();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'Second, later comment.',
        ]);

        $this->assertSame('First comment.', $first->refresh()->content);
    }

    public function test_each_comment_is_independently_moderated(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'This one will be approved.',
        ]);
        $firstComment = Review::query()->first();
        $firstComment->update(['status' => ReviewStatus::Approved]);

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'This one stays pending.',
        ]);

        $response = $this->get($track->reviewUrl());

        $response->assertSee('This one will be approved.');
        $response->assertDontSee('This one stays pending.');
    }

    /**
     * A legacy row nobody has resubmitted yet keeps its real rating intact
     * — the migration that made `rating` nullable never touches existing
     * data (see database/migrations/2026_09_02_120000_make_reviews_rating_nullable.php).
     */
    public function test_an_untouched_legacy_rated_review_keeps_its_rating(): void
    {
        $track = $this->publishedSingleTrack();
        $legacy = $this->createApprovedComment($track, User::factory()->create(), 'An old rated review nobody edited.');
        $legacy->update(['rating' => 4]);

        $this->assertSame(4, $legacy->refresh()->rating);
    }

    public function test_reviews_for_one_track_never_appear_on_another_tracks_page(): void
    {
        $singleA = $this->single(['title' => 'Track A Single', 'slug' => 'track-a-single', 'status' => ReleaseStatus::Published]);
        $trackA = $this->track(null, $singleA, ['title' => 'Track A', 'slug' => 'track-a', 'status' => TrackStatus::Published]);
        $singleB = $this->single(['title' => 'Track B Single', 'slug' => 'track-b-single', 'status' => ReleaseStatus::Published]);
        $trackB = $this->track(null, $singleB, ['title' => 'Track B', 'slug' => 'track-b', 'status' => TrackStatus::Published]);

        $this->createApprovedComment($trackA, User::factory()->create(), 'A comment that only belongs to Track A.');

        $responseA = $this->get(route('music.singles.show', $singleA));
        $responseA->assertSee('A comment that only belongs to Track A.');

        $responseB = $this->get(route('music.singles.show', $singleB));
        $responseB->assertDontSee('A comment that only belongs to Track A.');
        $responseB->assertSee('Be the first to share your thoughts');
    }

    public function test_an_album_owned_tracks_reviews_are_scoped_to_that_track_only(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $trackOne = $this->track($album, null, ['title' => 'Track One', 'slug' => 'track-one', 'status' => TrackStatus::Published, 'track_number' => 1]);
        $trackTwo = $this->track($album, null, ['title' => 'Track Two', 'slug' => 'track-two', 'status' => TrackStatus::Published, 'track_number' => 2]);

        $this->createApprovedComment($trackOne, User::factory()->create(), 'Comment for track one only.');

        $responseOne = $this->get(route('music.tracks.show', $trackOne));
        $responseOne->assertSee('Comment for track one only.');

        $responseTwo = $this->get(route('music.tracks.show', $trackTwo));
        $responseTwo->assertDontSee('Comment for track one only.');
    }

    public function test_the_comment_count_uses_only_approved_reviews(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);
        $this->createApprovedComment($track, User::factory()->create(), 'Excellent song.');
        $this->createApprovedComment($track, User::factory()->create(), 'It was fine.');
        Review::query()->create([
            'reviewable_type' => Track::class,
            'reviewable_id' => $track->id,
            'user_id' => User::factory()->create()->id,
            'rating' => null,
            'content' => 'A pending comment that must not count yet.',
            'status' => ReviewStatus::Pending,
        ]);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertSee('2 comments');
        $response->assertDontSee('A pending comment that must not count yet.');
    }

    public function test_no_star_rating_language_or_widget_appears_on_the_page(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);
        $this->createApprovedComment($track, User::factory()->create(), 'A comment.');

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertDontSee('data-review-star', false);
        $response->assertDontSee('data-review-rating', false);
        $response->assertDontSee('Leave a Review');
        $response->assertDontSee('Submit Review');
        $response->assertDontSee('average', false);
    }

    public function test_the_reviews_section_appears_on_a_singles_listening_page(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $this->track(null, $single, ['status' => TrackStatus::Published]);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertSee('What Listeners Are Saying');
    }

    public function test_the_reviews_section_appears_on_an_album_owned_tracks_page(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['status' => TrackStatus::Published]);

        $response = $this->get(route('music.tracks.show', $track));

        $response->assertOk();
        $response->assertSee('What Listeners Are Saying');
    }

    public function test_the_reviews_section_does_not_appear_on_an_album_listening_page(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $this->track($album, null, ['status' => TrackStatus::Published]);

        $response = $this->get(route('music.albums.show', $album));

        $response->assertOk();
        $response->assertDontSee('What Listeners Are Saying');
    }

    public function test_the_empty_review_state_shows_no_fabricated_content(): void
    {
        $track = $this->publishedSingleTrack();

        $response = $this->get($track->reviewUrl());

        $response->assertOk();
        $response->assertSee('Be the first to share your thoughts');
    }

    public function test_a_guest_sees_a_login_prompt_instead_of_the_submission_form(): void
    {
        $track = $this->publishedSingleTrack();

        $response = $this->get($track->reviewUrl());

        $response->assertOk();
        $response->assertSee('to leave a comment');
        $response->assertDontSee('data-review-form', false);
    }

    public function test_a_reviewer_with_no_avatar_shows_the_placeholder_image(): void
    {
        $track = $this->publishedSingleTrack();
        $this->createApprovedComment($track, User::factory()->create(), 'No avatar set for this reviewer.');

        $response = $this->get($track->reviewUrl());

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

        $track = $this->publishedSingleTrack();
        $this->createApprovedComment($track, $user, 'This reviewer has a real avatar.');

        $response = $this->get($track->reviewUrl());

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url($avatar->path), false);
    }

    // --- Honeypot spam protection (reuses ContactController::store()'s exact pattern) ---

    public function test_a_legitimate_comment_submission_succeeds(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'A perfectly normal comment.',
            'hp_website' => '',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Review::query()->count());
    }

    public function test_a_honeypot_triggering_submission_is_silently_discarded(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'A bot filled the honeypot field.',
            'hp_website' => 'https://spam.example.com',
        ]);

        // The bot gets the same success response a real visitor would — no
        // signal it was caught.
        $response->assertRedirect();
        $response->assertSessionHas('review_status');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_comments_are_disabled_when_the_module_config_is_off(): void
    {
        config(['features.music_comments_enabled' => false]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'content' => 'This should never be persisted.',
        ]);

        $response->assertNotFound();
        $this->assertSame(0, Review::query()->count());
    }

    public function test_the_comment_ui_is_hidden_when_music_comments_are_disabled(): void
    {
        config(['features.music_comments_enabled' => false]);
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);
        $this->createApprovedComment($track, User::factory()->create(), 'A comment that must not render.');

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertDontSee('A comment that must not render.');
        $response->assertDontSee('data-review-form', false);
        $response->assertDontSee('Leave a Comment');
    }

    private function createApprovedComment(Track $track, User $user, string $content): Review
    {
        return Review::query()->create([
            'reviewable_type' => Track::class,
            'reviewable_id' => $track->id,
            'user_id' => $user->id,
            'rating' => null,
            'content' => $content,
            'status' => ReviewStatus::Approved,
        ]);
    }

    private function publishedSingleTrack(array $overrides = []): Track
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);

        return $this->track(null, $single, ['status' => TrackStatus::Published, ...$overrides]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function album(array $overrides = []): Album
    {
        return Album::query()->create([
            'title' => 'An Album',
            'slug' => 'an-album-'.uniqid(),
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function single(array $overrides = []): Single
    {
        return Single::query()->create([
            'title' => 'A Single',
            'slug' => 'a-single-'.uniqid(),
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function track(?Album $album, ?Single $single, array $overrides = []): Track
    {
        return Track::query()->create([
            'album_id' => $album?->id,
            'single_id' => $single?->id,
            'title' => 'A Track',
            'slug' => 'a-track-'.uniqid(),
            'status' => TrackStatus::Draft,
            ...$overrides,
        ]);
    }
}
