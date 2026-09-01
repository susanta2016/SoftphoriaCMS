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
 * Reviews/ratings on a Music Track (a Single's own song, or an Album-owned
 * track) — via the exact same shared App\Models\Review /
 * App\Actions\Review\SubmitReviewAction the Podcast module already uses
 * (see Tests\Feature\Podcast\ReviewSubmissionTest, the reference this test
 * mirrors). Reviews belong to the individual Track, never the Album as a
 * whole — a Single's "track" is its one underlying Track row (Single::
 * track()), so both entry points are exercised here.
 */
class TrackReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_submit_a_review_and_is_redirected_to_login(): void
    {
        $track = $this->publishedSingleTrack();

        $response = $this->post(route('music.tracks.reviews.store', $track), [
            'rating' => 5,
            'content' => 'Guests should not be able to post this.',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_registered_user_can_submit_a_rating_and_review(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'rating' => 4,
            'content' => 'A genuinely thoughtful review of this song.',
        ]);

        $response->assertRedirect();
        $review = Review::query()->first();
        $this->assertNotNull($review);
        $this->assertSame($user->getKey(), $review->user_id);
        $this->assertSame($track->getKey(), $review->reviewable_id);
        $this->assertSame(Track::class, $review->reviewable_type);
        $this->assertSame(4, $review->rating);
    }

    public function test_rating_must_be_between_1_and_5(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'rating' => 6,
            'content' => 'Valid content but an invalid rating.',
        ]);

        $response->assertSessionHasErrors('rating');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_review_content_is_required(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'rating' => 5,
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
            'rating' => 5,
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
            'rating' => 5,
            'content' => str_repeat('a', 301),
        ]);

        $response->assertSessionHasErrors('content');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_review_is_pending_when_admin_approval_is_required(): void
    {
        config(['reviews.reviews_ratings_admin_approval' => true]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'rating' => 5,
            'content' => 'Pending review content.',
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
            'rating' => 5,
            'content' => 'A distinctive pending review sentinel.',
            'status' => ReviewStatus::Pending,
        ]);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertDontSee('A distinctive pending review sentinel');
        $response->assertSee('Be the first to share your thoughts');
    }

    public function test_an_approved_review_becomes_publicly_visible(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);
        $this->createApprovedReview($track, User::factory()->create(), 5, 'A wonderful, approved review.');

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertSee('A wonderful, approved review.');
    }

    public function test_admin_approval_disabled_publishes_immediately_and_sends_the_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        config(['reviews.reviews_ratings_admin_approval' => false]);
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'rating' => 5,
            'content' => 'An immediately published review.',
        ]);

        $this->assertSame(ReviewStatus::Approved, Review::query()->first()->status);

        $response = $this->get($track->reviewUrl());
        $response->assertSee('An immediately published review.');
        Mail::assertSent(TemplatedNotificationMail::class, fn ($mail): bool => $mail->hasTo($user->email));
    }

    public function test_resubmitting_updates_the_existing_review_rather_than_creating_a_duplicate(): void
    {
        $track = $this->publishedSingleTrack();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'rating' => 3,
            'content' => 'First submission.',
        ]);
        $this->actingAs($user)->post(route('music.tracks.reviews.store', $track), [
            'rating' => 5,
            'content' => 'Updated submission.',
        ]);

        $this->assertSame(1, Review::query()->count());
        $review = Review::query()->first();
        $this->assertSame(5, $review->rating);
        $this->assertSame('Updated submission.', $review->content);
    }

    public function test_reviews_for_one_track_never_appear_on_another_tracks_page(): void
    {
        $singleA = $this->single(['title' => 'Track A Single', 'slug' => 'track-a-single', 'status' => ReleaseStatus::Published]);
        $trackA = $this->track(null, $singleA, ['title' => 'Track A', 'slug' => 'track-a', 'status' => TrackStatus::Published]);
        $singleB = $this->single(['title' => 'Track B Single', 'slug' => 'track-b-single', 'status' => ReleaseStatus::Published]);
        $trackB = $this->track(null, $singleB, ['title' => 'Track B', 'slug' => 'track-b', 'status' => TrackStatus::Published]);

        $this->createApprovedReview($trackA, User::factory()->create(), 5, 'A review that only belongs to Track A.');

        $responseA = $this->get(route('music.singles.show', $singleA));
        $responseA->assertSee('A review that only belongs to Track A.');

        $responseB = $this->get(route('music.singles.show', $singleB));
        $responseB->assertDontSee('A review that only belongs to Track A.');
        $responseB->assertSee('Be the first to share your thoughts');
    }

    public function test_an_album_owned_tracks_reviews_are_scoped_to_that_track_only(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $trackOne = $this->track($album, null, ['title' => 'Track One', 'slug' => 'track-one', 'status' => TrackStatus::Published, 'track_number' => 1]);
        $trackTwo = $this->track($album, null, ['title' => 'Track Two', 'slug' => 'track-two', 'status' => TrackStatus::Published, 'track_number' => 2]);

        $this->createApprovedReview($trackOne, User::factory()->create(), 4, 'Review for track one only.');

        $responseOne = $this->get(route('music.tracks.show', $trackOne));
        $responseOne->assertSee('Review for track one only.');

        $responseTwo = $this->get(route('music.tracks.show', $trackTwo));
        $responseTwo->assertDontSee('Review for track one only.');
    }

    public function test_the_rating_average_and_count_use_only_approved_reviews(): void
    {
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['status' => TrackStatus::Published]);
        $this->createApprovedReview($track, User::factory()->create(), 5, 'Excellent song.');
        $this->createApprovedReview($track, User::factory()->create(), 3, 'It was fine.');
        Review::query()->create([
            'reviewable_type' => Track::class,
            'reviewable_id' => $track->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 1,
            'content' => 'A pending review that must not skew the average.',
            'status' => ReviewStatus::Pending,
        ]);

        $response = $this->get(route('music.singles.show', $single));

        $response->assertOk();
        $response->assertSee('4 average'); // (5+3)/2 rounded to one decimal renders as 4
        $response->assertSee('2 reviews');
        $response->assertDontSee('A pending review that must not skew the average.');
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
        $response->assertSee('to leave a rating and review');
        $response->assertDontSee('data-review-form', false);
    }

    public function test_a_reviewer_with_no_avatar_shows_the_placeholder_image(): void
    {
        $track = $this->publishedSingleTrack();
        $this->createApprovedReview($track, User::factory()->create(), 5, 'No avatar set for this reviewer.');

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
        $this->createApprovedReview($track, $user, 5, 'This reviewer has a real avatar.');

        $response = $this->get($track->reviewUrl());

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url($avatar->path), false);
    }

    private function createApprovedReview(Track $track, User $user, int $rating, string $content): Review
    {
        return Review::query()->create([
            'reviewable_type' => Track::class,
            'reviewable_id' => $track->id,
            'user_id' => $user->id,
            'rating' => $rating,
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
