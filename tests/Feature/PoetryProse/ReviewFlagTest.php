<?php

namespace Tests\Feature\PoetryProse;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\ReviewFlag;
use App\Models\Role;
use App\Models\User;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The 🚩 "report this comment" action on a Poetry/Prose entry comment —
 * fully independent of PoetryProseReactionTest's 🙌 coverage (a member can
 * react, comment, and/or report, all independently). App\Models\ReviewFlag
 * is a separate table/model from both App\Models\Review and
 * App\Models\Reaction, shared with Podcast/Music via the same generic
 * App\Actions\Review\FlagReviewAction — this test exists only to confirm
 * Poetry/Prose's own controller/route wiring, mirroring
 * Tests\Feature\PoetryProse\PoetryProseReviewTest's own reasoning.
 */
class ReviewFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_report_a_comment_and_is_redirected_to_login(): void
    {
        $entry = $this->entry();
        $review = $this->comment($entry, User::factory()->create());

        $response = $this->post(route('poetry-prose.reviews.flag', [$entry, $review]));

        $response->assertRedirect(route('login'));
        $this->assertSame(0, ReviewFlag::query()->count());
    }

    public function test_an_authenticated_user_can_report_a_comment(): void
    {
        $entry = $this->entry();
        $author = User::factory()->create();
        $review = $this->comment($entry, $author);
        $reporter = User::factory()->create();

        $response = $this->actingAs($reporter)->post(route('poetry-prose.reviews.flag', [$entry, $review]));

        $response->assertRedirect();
        $this->assertSame(1, ReviewFlag::query()->count());
        $flag = ReviewFlag::query()->first();
        $this->assertSame($reporter->getKey(), $flag->user_id);
        $this->assertSame($review->getKey(), $flag->review_id);
    }

    public function test_the_async_endpoint_returns_an_appropriate_json_response(): void
    {
        $entry = $this->entry();
        $review = $this->comment($entry, User::factory()->create());
        $reporter = User::factory()->create();

        $response = $this->actingAs($reporter)->postJson(route('poetry-prose.reviews.flag', [$entry, $review]));

        $response->assertOk();
        $response->assertJson(['flagged' => true, 'already' => false]);
    }

    public function test_a_second_report_from_the_same_user_is_a_no_op(): void
    {
        $entry = $this->entry();
        $review = $this->comment($entry, User::factory()->create());
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->postJson(route('poetry-prose.reviews.flag', [$entry, $review]));
        $response = $this->actingAs($reporter)->postJson(route('poetry-prose.reviews.flag', [$entry, $review]));

        $response->assertOk();
        $response->assertJson(['flagged' => true, 'already' => true]);
        $this->assertSame(1, ReviewFlag::query()->count());
    }

    public function test_two_different_users_can_each_report_the_same_comment(): void
    {
        $entry = $this->entry();
        $review = $this->comment($entry, User::factory()->create());
        $reporterA = User::factory()->create();
        $reporterB = User::factory()->create();

        $this->actingAs($reporterA)->post(route('poetry-prose.reviews.flag', [$entry, $review]));
        $this->actingAs($reporterB)->post(route('poetry-prose.reviews.flag', [$entry, $review]));

        $this->assertSame(2, ReviewFlag::query()->count());
    }

    public function test_reporting_sends_the_comment_author_and_every_admin_an_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $entry = $this->entry();
        $author = User::factory()->create();
        $review = $this->comment($entry, $author);
        $reporter = User::factory()->create();
        $admin = $this->admin();

        $this->actingAs($reporter)->post(route('poetry-prose.reviews.flag', [$entry, $review]));

        Mail::assertSent(TemplatedNotificationMail::class, fn ($mail): bool => $mail->hasTo($author->email));
        Mail::assertSent(TemplatedNotificationMail::class, fn ($mail): bool => $mail->hasTo($admin->email));
    }

    public function test_a_repeat_report_from_the_same_user_does_not_send_another_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $entry = $this->entry();
        $review = $this->comment($entry, User::factory()->create());
        $reporter = User::factory()->create();
        $this->admin();

        $this->actingAs($reporter)->post(route('poetry-prose.reviews.flag', [$entry, $review]));
        Mail::assertSentCount(2); // author + admin, once each

        $this->actingAs($reporter)->post(route('poetry-prose.reviews.flag', [$entry, $review]));
        Mail::assertSentCount(2); // unchanged — the second report was a no-op
    }

    public function test_reporting_a_comment_from_another_entry_returns_404(): void
    {
        $entryA = $this->entry();
        $entryB = $this->entry();
        $review = $this->comment($entryA, User::factory()->create());
        $reporter = User::factory()->create();

        $response = $this->actingAs($reporter)->post(route('poetry-prose.reviews.flag', [$entryB, $review]));

        $response->assertNotFound();
        $this->assertSame(0, ReviewFlag::query()->count());
    }

    public function test_reporting_on_an_unpublished_entry_returns_404(): void
    {
        $entry = $this->entry(['status' => PoetryProseStatus::Draft]);
        $review = $this->comment($entry, User::factory()->create());
        $reporter = User::factory()->create();

        $response = $this->actingAs($reporter)->post(route('poetry-prose.reviews.flag', [$entry, $review]));

        $response->assertNotFound();
    }

    public function test_a_reported_comment_shows_a_reported_state_to_the_reporter(): void
    {
        $entry = $this->entry();
        $review = $this->comment($entry, User::factory()->create());
        $reporter = User::factory()->create();
        ReviewFlag::query()->create(['review_id' => $review->id, 'user_id' => $reporter->id]);

        $response = $this->actingAs($reporter)->get(route('poetry-prose.show', $entry));

        $response->assertOk();
        $response->assertSee('Reported');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    private function comment(PoetryProse $entry, User $user): Review
    {
        return Review::query()->create([
            'reviewable_type' => PoetryProse::class,
            'reviewable_id' => $entry->id,
            'user_id' => $user->id,
            'rating' => null,
            'content' => 'A comment that might get reported.',
            'status' => ReviewStatus::Approved,
        ]);
    }

    private function entry(array $overrides = []): PoetryProse
    {
        return PoetryProse::query()->create([
            'title' => 'Flag Test Entry',
            'slug' => 'flag-test-entry-'.uniqid(),
            'body' => '<p>Body content.</p>',
            'content_type' => PoetryProseContentType::Essay,
            'status' => PoetryProseStatus::Published,
            'publish_at' => now(),
            ...$overrides,
        ]);
    }
}
