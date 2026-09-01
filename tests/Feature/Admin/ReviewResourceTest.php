<?php

namespace Tests\Feature\Admin;

use App\Actions\Review\PublishReviewAction;
use App\Actions\Review\RejectReviewAction;
use App\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
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

    private function createReview(): Review
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
