<?php

namespace Tests\Feature\InspirationalResources;

use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public Inspirational Resources listing + detail pages (client-confirmed
 * reversal, 2026-09-02) — mirrors Poetry/Prose's landing page (search/
 * category/sort/pagination, sidebar), minus a hero banner, showing only
 * Approved submissions. Everything before Approved (Submitted/InReview)
 * and Archived stays private — see InspirationalResourceSubmissionTest for
 * the separate "Submit Your Writing" form page.
 */
class InspirationalResourceListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_listing_page_shows_approved_submissions_only(): void
    {
        $approved = $this->submission(['subject' => 'An Approved Story', 'slug' => 'an-approved-story', 'status' => ResourceSubmissionStatus::Approved]);
        $pending = $this->submission(['subject' => 'A Pending Story', 'slug' => 'a-pending-story', 'status' => ResourceSubmissionStatus::Submitted]);
        $archived = $this->submission(['subject' => 'An Archived Story', 'slug' => 'an-archived-story', 'status' => ResourceSubmissionStatus::Archived]);

        $response = $this->get(route('inspirational-resources.index'));

        $response->assertOk();
        $response->assertSee('An Approved Story');
        $response->assertDontSee('A Pending Story');
        $response->assertDontSee('An Archived Story');
    }

    public function test_the_listing_page_shows_an_empty_state_when_there_are_no_approved_submissions(): void
    {
        $response = $this->get(route('inspirational-resources.index'));

        $response->assertOk();
        $response->assertSee('No stories to show yet');
    }

    public function test_the_listing_page_offers_a_submit_your_writing_link(): void
    {
        $response = $this->get(route('inspirational-resources.index'));

        $response->assertOk();
        $response->assertSee('Submit Your Writing');
        $response->assertSee(route('inspirational-resources.create'), false);
    }

    public function test_the_listing_page_search_filters_by_subject(): void
    {
        $match = $this->submission(['subject' => 'Zebra Migration Reflections '.uniqid(), 'slug' => 'zebra-'.uniqid(), 'status' => ResourceSubmissionStatus::Approved]);
        $other = $this->submission(['subject' => 'Unrelated Topic '.uniqid(), 'slug' => 'unrelated-'.uniqid(), 'status' => ResourceSubmissionStatus::Approved]);

        $response = $this->get(route('inspirational-resources.index', ['q' => 'Zebra Migration']));

        $response->assertOk();
        $response->assertViewHas('submissions', fn ($submissions) => $submissions->pluck('id')->contains($match->id)
            && ! $submissions->pluck('id')->contains($other->id));
    }

    public function test_an_ajax_request_returns_only_the_results_partial(): void
    {
        $submission = $this->submission(['subject' => 'Ajax Partial Story '.uniqid(), 'slug' => 'ajax-partial-'.uniqid(), 'status' => ResourceSubmissionStatus::Approved]);

        $response = $this->get(route('inspirational-resources.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertSee($submission->publicTitle());
        // The partial has no <html>/<head> — only a real full-page request
        // renders the layout shell.
        $response->assertDontSee('<!DOCTYPE html>', false);
    }

    public function test_the_listing_page_filters_by_category(): void
    {
        $match = $this->submission(['category' => 'Encouragement', 'slug' => 'match-'.uniqid(), 'status' => ResourceSubmissionStatus::Approved]);
        $other = $this->submission(['category' => 'Testimony', 'slug' => 'other-'.uniqid(), 'status' => ResourceSubmissionStatus::Approved]);

        $response = $this->get(route('inspirational-resources.index', ['category' => 'Encouragement']));

        $response->assertOk();
        $response->assertViewHas('submissions', fn ($submissions) => $submissions->pluck('id')->contains($match->id)
            && ! $submissions->pluck('id')->contains($other->id));
    }

    public function test_a_published_submission_is_publicly_viewable(): void
    {
        $submission = $this->submission([
            'subject' => 'My Approved Story',
            'message' => 'The full story text goes here.',
            'slug' => 'my-approved-story',
            'status' => ResourceSubmissionStatus::Approved,
        ]);

        $response = $this->get(route('inspirational-resources.show', $submission));

        $response->assertOk();
        $response->assertSee('My Approved Story');
        $response->assertSee('The full story text goes here.');
    }

    public function test_the_detail_page_shows_the_submitters_name_but_never_their_email(): void
    {
        $submission = $this->submission([
            'name' => 'Jane Public',
            'email' => 'jane-private@example.com',
            'slug' => 'name-not-email',
            'status' => ResourceSubmissionStatus::Approved,
        ]);

        $response = $this->get(route('inspirational-resources.show', $submission));

        $response->assertOk();
        $response->assertSee('Jane Public');
        $response->assertDontSee('jane-private@example.com');
    }

    public function test_the_detail_page_shows_the_reference_url_when_present(): void
    {
        $submission = $this->submission([
            'reference_url' => 'https://example.com/the-song',
            'slug' => 'has-a-reference',
            'status' => ResourceSubmissionStatus::Approved,
        ]);

        $response = $this->get(route('inspirational-resources.show', $submission));

        $response->assertOk();
        $response->assertSee('https://example.com/the-song');
    }

    public function test_a_submission_without_a_subject_falls_back_to_a_submitter_attributed_title(): void
    {
        $submission = $this->submission([
            'name' => 'Anonymous Storyteller',
            'subject' => null,
            'slug' => 'no-subject-here',
            'status' => ResourceSubmissionStatus::Approved,
        ]);

        $response = $this->get(route('inspirational-resources.show', $submission));

        $response->assertOk();
        $response->assertSee('A Story from Anonymous Storyteller');
    }

    public function test_a_pending_submission_404s_publicly(): void
    {
        $submission = $this->submission(['slug' => 'still-pending', 'status' => ResourceSubmissionStatus::Submitted]);

        $response = $this->get(route('inspirational-resources.show', $submission));

        $response->assertNotFound();
    }

    public function test_an_archived_submission_404s_publicly(): void
    {
        $submission = $this->submission(['slug' => 'now-archived', 'status' => ResourceSubmissionStatus::Archived]);

        $response = $this->get(route('inspirational-resources.show', $submission));

        $response->assertNotFound();
    }

    public function test_the_detail_page_links_to_the_previous_and_next_approved_submissions(): void
    {
        $first = $this->submission(['subject' => 'First Story', 'slug' => 'first-story', 'status' => ResourceSubmissionStatus::Approved, 'created_at' => now()->subDays(2)]);
        $middle = $this->submission(['subject' => 'Middle Story', 'slug' => 'middle-story', 'status' => ResourceSubmissionStatus::Approved, 'created_at' => now()->subDay()]);
        $last = $this->submission(['subject' => 'Last Story', 'slug' => 'last-story', 'status' => ResourceSubmissionStatus::Approved, 'created_at' => now()]);

        $response = $this->get(route('inspirational-resources.show', $middle));

        $response->assertOk();
        $response->assertSee('First Story');
        $response->assertSee('Last Story');
    }

    public function test_sitemap_includes_an_approved_submission(): void
    {
        $submission = $this->submission(['slug' => 'sitemap-included-story', 'status' => ResourceSubmissionStatus::Approved]);

        $response = $this->get('/sitemap.xml');

        $response->assertSee(route('inspirational-resources.show', $submission), false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submission(array $overrides = []): ResourceSubmission
    {
        return ResourceSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'A Story',
            'category' => 'Testimony',
            'message' => 'A story worth sharing.',
            'slug' => 'a-story-'.uniqid(),
            'status' => ResourceSubmissionStatus::Submitted,
            ...$overrides,
        ]);
    }
}
