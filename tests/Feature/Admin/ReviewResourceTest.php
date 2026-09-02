<?php

namespace Tests\Feature\Admin;

use App\Actions\Review\PublishReviewAction;
use App\Actions\Review\RejectReviewAction;
use App\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Pages\ViewReview;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin moderation of the generic, reusable App\Models\Review — access
 * control follows the app's one existing convention (any "admin"-role user;
 * there is no finer-grained per-resource authorization anywhere else in
 * this app to mirror). List + View only, same reasoning as
 * ResourceSubmissionResource: a review is always submitted from the public
 * site, never hand-authored by an admin.
 */
class ReviewResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_reviews(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/reviews');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_list(): void
    {
        $review = $this->createReview();

        Livewire::actingAs($this->admin())
            ->test(ListReviews::class)
            ->assertCanSeeTableRecords([$review]);
    }

    public function test_no_create_or_edit_route_exists(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/reviews/create');

        $response->assertNotFound();
    }

    /**
     * Client-confirmed reversal (2026-09-02): the admin-facing label reads
     * "Light Posts & Comments" — the underlying resource/route/model stay
     * named Review/ReviewResource/reviews internally (no large-scale
     * rename), only what an admin actually sees changes.
     */
    public function test_the_admin_navigation_label_is_light_posts_and_comments(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/reviews');

        $response->assertOk();
        $response->assertSee('Light Posts &amp; Comments', false);
    }

    /**
     * Client-confirmed reversal (2026-09-02): star ratings are no longer
     * part of the active feature — the normal moderation list must not
     * show a Rating column or any star glyphs at all. A handful of legacy
     * rows still carry a real rating value, kept visible only on their own
     * detail page's "Legacy Rating" section (see ReviewInfolist) — never
     * in this list.
     */
    public function test_the_rating_column_does_not_appear_in_the_normal_list(): void
    {
        $this->createReview(['rating' => 4]);

        $response = $this->actingAs($this->admin())->get('/admin/reviews');

        $response->assertOk();
        $response->assertDontSee('Rating');
        $response->assertDontSee('★');
    }

    public function test_admin_can_approve_publish_a_pending_review(): void
    {
        $review = $this->createReview();

        app(PublishReviewAction::class)->handle($review);

        $this->assertSame(ReviewStatus::Approved, $review->refresh()->status);
    }

    public function test_approving_sends_the_review_published_email_exactly_once(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $review = $this->createReview();

        app(PublishReviewAction::class)->handle($review);

        Mail::assertSent(TemplatedNotificationMail::class, 1);
        Mail::assertSent(TemplatedNotificationMail::class, fn ($mail): bool => $mail->hasTo($review->user->email));
    }

    public function test_re_approving_an_already_published_review_does_not_resend_the_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $review = $this->createReview();

        app(PublishReviewAction::class)->handle($review);
        // An unrelated admin edit/re-save of an already-approved review.
        app(PublishReviewAction::class)->handle($review->refresh());

        Mail::assertSent(TemplatedNotificationMail::class, 1);
    }

    public function test_admin_can_reject_a_review_and_no_email_is_sent(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $review = $this->createReview();

        app(RejectReviewAction::class)->handle($review);

        $this->assertSame(ReviewStatus::Rejected, $review->refresh()->status);
        Mail::assertNothingSent();
    }

    /**
     * Confirms the shared ReviewResource (never a Music-specific admin
     * resource — see ReviewResource's docblock) manages a Music Track
     * review through the exact same Approve/Reject actions Podcast uses,
     * and that the "Content Type" column/state distinguishes it from a
     * Podcast review moderated alongside it.
     */
    public function test_admin_can_view_and_approve_a_music_track_review_distinguished_from_podcast(): void
    {
        $podcastReview = $this->createReview();
        $trackReview = $this->createTrackReview();

        Livewire::actingAs($this->admin())
            ->test(ListReviews::class)
            ->assertCanSeeTableRecords([$podcastReview, $trackReview]);

        $this->assertSame('Podcast Episode', $podcastReview->reviewableType());
        $this->assertSame('Track', $trackReview->reviewableType());

        app(PublishReviewAction::class)->handle($trackReview);

        $this->assertSame(ReviewStatus::Approved, $trackReview->refresh()->status);
    }

    /**
     * A new comment-only submission (rating = null, client-confirmed
     * reversal 2026-09-02) must render without error in both the list and
     * the view/infolist — ReviewsTable/ReviewInfolist's rating column is
     * null-safe specifically for this case.
     */
    public function test_a_null_rating_comment_renders_without_error_in_the_admin_list_and_view(): void
    {
        $review = $this->createReview(['rating' => null, 'content' => 'A comment-only submission with no rating.']);

        Livewire::actingAs($this->admin())
            ->test(ListReviews::class)
            ->assertCanSeeTableRecords([$review])
            ->assertSuccessful();

        Livewire::actingAs($this->admin())
            ->test(ViewReview::class, ['record' => $review->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('A comment-only submission with no rating.');
    }

    /**
     * A pre-existing legacy row with a real rating must keep displaying it
     * (as stars) rather than being coerced to the null-rating placeholder —
     * confirms the two cases coexist in the same table/infolist.
     */
    public function test_a_legacy_rated_review_still_displays_its_rating_in_the_admin_view(): void
    {
        $review = $this->createReview(['rating' => 4]);

        Livewire::actingAs($this->admin())
            ->test(ViewReview::class, ['record' => $review->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('★★★★☆');
    }

    private function createTrackReview(): Review
    {
        $single = Single::query()->create([
            'title' => 'Admin Review Test Single '.uniqid(),
            'slug' => 'admin-review-test-single-'.uniqid(),
            'status' => ReleaseStatus::Published,
        ]);

        $track = Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Admin Review Test Track',
            'slug' => 'admin-review-test-track-'.uniqid(),
            'status' => TrackStatus::Published,
        ]);

        return Review::query()->create([
            'reviewable_type' => Track::class,
            'reviewable_id' => $track->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 4,
            'content' => 'A Music review awaiting moderation.',
            'status' => ReviewStatus::Pending,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createReview(array $overrides = []): Review
    {
        $podcast = Podcast::query()->create([
            'title' => 'Admin Review Test Podcast '.uniqid(),
            'slug' => 'admin-review-test-podcast-'.uniqid(),
            'status' => PodcastStatus::Published,
        ]);

        $episode = PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Admin Review Test Episode',
            'slug' => 'admin-review-test-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
        ]);

        return Review::query()->create([
            'reviewable_type' => PodcastEpisode::class,
            'reviewable_id' => $episode->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 5,
            'content' => 'A review awaiting moderation.',
            'status' => ReviewStatus::Pending,
            ...$overrides,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
