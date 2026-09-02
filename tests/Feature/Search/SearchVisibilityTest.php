<?php

namespace Tests\Feature\Search;

use App\Models\LightPost;
use App\Models\User;
use App\Modules\InspirationalResources\Enums\ResourceSubmissionStatus;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unified search (GET /search) must only ever surface the same public
 * subset each model's own public controller already shows — covers every
 * scenario the client's revised audit explicitly listed: published/
 * unpublished/draft/pending/rejected/private per model, the confirmed
 * Track canonical-URL rule (album-owned only), and publication-state
 * transitions. Every assertion reads the search results view's own `results`
 * data rather than scanning rendered HTML, so a title collision with
 * unrelated page chrome can never produce a false pass.
 */
class SearchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_album_appears_in_search_results(): void
    {
        $album = $this->album(['title' => 'Zenith Horizon '.uniqid(), 'status' => ReleaseStatus::Published]);

        $this->assertSearchContainsUrl('Zenith Horizon', route('music.albums.show', $album));
    }

    public function test_a_draft_album_does_not_appear_in_search_results(): void
    {
        $album = $this->album(['title' => 'Hidden Draft Album '.uniqid(), 'status' => ReleaseStatus::Draft]);

        $this->assertSearchExcludesTitle('Hidden Draft Album', $album->title);
    }

    public function test_a_published_single_appears_in_search_results(): void
    {
        $single = $this->single(['title' => 'Quiet Morning '.uniqid(), 'status' => ReleaseStatus::Published]);

        $this->assertSearchContainsUrl('Quiet Morning', route('music.singles.show', $single));
    }

    public function test_a_draft_single_does_not_appear_in_search_results(): void
    {
        $single = $this->single(['title' => 'Hidden Draft Single '.uniqid(), 'status' => ReleaseStatus::Draft]);

        $this->assertSearchExcludesTitle('Hidden Draft Single', $single->title);
    }

    public function test_an_album_owned_published_track_appears_in_search_results_with_its_own_url(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['title' => 'Anthem of Dawn '.uniqid(), 'status' => TrackStatus::Published]);

        $this->assertSearchContainsUrl('Anthem of Dawn', route('music.tracks.show', $track));
    }

    /**
     * The confirmed canonical-URL rule (revised audit §3): a single-owned
     * track has no page of its own — MusicController::showTrack() 301s to
     * the Single — so it must never surface as its own search result. Its
     * content is already covered by indexing the Single itself.
     */
    public function test_a_single_owned_track_does_not_appear_as_its_own_search_result(): void
    {
        $single = $this->single(['title' => 'Its Single Title '.uniqid(), 'status' => ReleaseStatus::Published]);
        $track = $this->track(null, $single, ['title' => 'Distinct Track Title '.uniqid(), 'status' => TrackStatus::Published]);

        $response = $this->get(route('search.index', ['q' => 'Distinct Track Title']));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => ! $results->getCollection()
            ->contains(fn ($result) => $result->url === route('music.tracks.show', $track)));
    }

    public function test_a_draft_track_does_not_appear_in_search_results(): void
    {
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $track = $this->track($album, null, ['title' => 'Hidden Draft Track '.uniqid(), 'status' => TrackStatus::Draft]);

        $this->assertSearchExcludesTitle('Hidden Draft Track', $track->title);
    }

    public function test_a_published_poetry_prose_entry_appears_in_search_results(): void
    {
        $entry = $this->poetryProse(['title' => 'Reflections on Light '.uniqid(), 'status' => PoetryProseStatus::Published]);

        $this->assertSearchContainsUrl('Reflections on Light', route('poetry-prose.show', $entry));
    }

    public function test_an_unpublished_poetry_prose_entry_does_not_appear_in_search_results(): void
    {
        $entry = $this->poetryProse(['title' => 'Hidden Draft Writing '.uniqid(), 'status' => PoetryProseStatus::Draft]);

        $this->assertSearchExcludesTitle('Hidden Draft Writing', $entry->title);
    }

    public function test_a_published_podcast_episode_appears_in_search_results(): void
    {
        $podcast = $this->podcast(['status' => PodcastStatus::Published]);
        $episode = $this->episode($podcast, ['title' => 'Voices of Hope '.uniqid(), 'status' => PodcastEpisodeStatus::Published]);

        $this->assertSearchContainsUrl('Voices of Hope', route('podcast.episodes.show', $episode));
    }

    public function test_an_unpublished_podcast_episode_does_not_appear_in_search_results(): void
    {
        $podcast = $this->podcast(['status' => PodcastStatus::Published]);
        $episode = $this->episode($podcast, ['title' => 'Hidden Draft Episode '.uniqid(), 'status' => PodcastEpisodeStatus::Draft]);

        $this->assertSearchExcludesTitle('Hidden Draft Episode', $episode->title);
    }

    /**
     * An episode's own status is Published, but its parent Podcast show is
     * not — mirrors PodcastEpisode::sitemapEntries()'s own whereHas
     * constraint. A public search result must never appear for content
     * whose normal public route would 404 for the same reason.
     */
    public function test_a_published_episode_of_a_draft_podcast_does_not_appear_in_search_results(): void
    {
        $podcast = $this->podcast(['status' => PodcastStatus::Draft]);
        $episode = $this->episode($podcast, ['title' => 'Orphaned Episode '.uniqid(), 'status' => PodcastEpisodeStatus::Published]);

        $this->assertSearchExcludesTitle('Orphaned Episode', $episode->title);
    }

    public function test_an_approved_resource_submission_appears_in_search_results(): void
    {
        $submission = $this->submission(['subject' => 'A Story of Grace '.uniqid(), 'status' => ResourceSubmissionStatus::Approved]);

        $this->assertSearchContainsUrl('A Story of Grace', route('inspirational-resources.show', $submission));
    }

    public function test_a_pending_resource_submission_does_not_appear_in_search_results(): void
    {
        $submission = $this->submission(['subject' => 'Pending Story '.uniqid(), 'status' => ResourceSubmissionStatus::Submitted]);

        $this->assertSearchExcludesTitle('Pending Story', $submission->subject);
    }

    public function test_an_in_review_resource_submission_does_not_appear_in_search_results(): void
    {
        $submission = $this->submission(['subject' => 'In Review Story '.uniqid(), 'status' => ResourceSubmissionStatus::InReview]);

        $this->assertSearchExcludesTitle('In Review Story', $submission->subject);
    }

    public function test_an_archived_resource_submission_does_not_appear_in_search_results(): void
    {
        $submission = $this->submission(['subject' => 'Archived Story '.uniqid(), 'status' => ResourceSubmissionStatus::Archived]);

        $this->assertSearchExcludesTitle('Archived Story', $submission->subject);
    }

    public function test_a_public_light_post_appears_in_search_results(): void
    {
        $user = User::factory()->create(['name' => 'Jordan Rivers']);
        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'content' => 'A wholly distinctive gratitude phrase '.uniqid(),
            'is_public' => true,
        ]);

        $response = $this->get(route('search.index', ['q' => 'wholly distinctive gratitude']));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => $results->getCollection()
            ->contains(fn ($result) => $result->url === route('light-posts.show', $post)));
    }

    public function test_a_private_light_post_does_not_appear_in_search_results(): void
    {
        $user = User::factory()->create();
        LightPost::query()->create([
            'user_id' => $user->id,
            'content' => 'A wholly private uniquely phrased thought '.uniqid(),
            'is_public' => false,
        ]);

        $response = $this->get(route('search.index', ['q' => 'wholly private uniquely phrased']));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => $results->getCollection()->isEmpty());
    }

    public function test_publishing_a_previously_draft_album_makes_it_appear_in_search_results(): void
    {
        $album = $this->album(['title' => 'Transitional Release '.uniqid(), 'status' => ReleaseStatus::Draft]);
        $this->assertSearchExcludesTitle('Transitional Release', $album->title);

        $album->update(['status' => ReleaseStatus::Published]);

        $this->assertSearchContainsUrl('Transitional Release', route('music.albums.show', $album));
    }

    public function test_unpublishing_a_previously_published_poetry_prose_entry_removes_it_from_search_results(): void
    {
        $entry = $this->poetryProse(['title' => 'Fading Reflection '.uniqid(), 'status' => PoetryProseStatus::Published]);
        $this->assertSearchContainsUrl('Fading Reflection', route('poetry-prose.show', $entry));

        $entry->update(['status' => PoetryProseStatus::Archived]);

        $this->assertSearchExcludesTitle('Fading Reflection', $entry->title);
    }

    public function test_approving_a_resource_submission_makes_it_appear_in_search_results(): void
    {
        $submission = $this->submission(['subject' => 'Newly Approved Story '.uniqid(), 'status' => ResourceSubmissionStatus::InReview]);
        $this->assertSearchExcludesTitle('Newly Approved Story', $submission->subject);

        $submission->update(['status' => ResourceSubmissionStatus::Approved]);

        $this->assertSearchContainsUrl('Newly Approved Story', route('inspirational-resources.show', $submission));
    }

    public function test_rejecting_makes_a_previously_approved_resource_submission_disappear_from_search_results(): void
    {
        $submission = $this->submission(['subject' => 'Later Archived Story '.uniqid(), 'status' => ResourceSubmissionStatus::Approved]);
        $this->assertSearchContainsUrl('Later Archived Story', route('inspirational-resources.show', $submission));

        $submission->update(['status' => ResourceSubmissionStatus::Archived]);

        $this->assertSearchExcludesTitle('Later Archived Story', $submission->subject);
    }

    public function test_making_a_light_post_private_removes_it_from_search_results(): void
    {
        $user = User::factory()->create();
        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'content' => 'A once public now hidden reflection '.uniqid(),
            'is_public' => true,
        ]);
        $response = $this->get(route('search.index', ['q' => 'once public now hidden']));
        $response->assertViewHas('results', fn ($results) => $results->getCollection()->isNotEmpty());

        $post->update(['is_public' => false]);

        $response = $this->get(route('search.index', ['q' => 'once public now hidden']));
        $response->assertViewHas('results', fn ($results) => $results->getCollection()->isEmpty());
    }

    private function assertSearchContainsUrl(string $query, string $expectedUrl): void
    {
        $response = $this->get(route('search.index', ['q' => $query]));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => $results->getCollection()
            ->contains(fn ($result) => $result->url === $expectedUrl));
    }

    private function assertSearchExcludesTitle(string $query, string $title): void
    {
        $response = $this->get(route('search.index', ['q' => $query]));

        $response->assertOk();
        $response->assertViewHas('results', fn ($results) => ! $results->getCollection()
            ->contains(fn ($result) => $result->title === $title));
    }

    private function album(array $overrides = []): Album
    {
        return Album::query()->create([
            'title' => 'An Album',
            'slug' => 'an-album-'.uniqid(),
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }

    private function single(array $overrides = []): Single
    {
        return Single::query()->create([
            'title' => 'A Single',
            'slug' => 'a-single-'.uniqid(),
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }

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

    private function poetryProse(array $overrides = []): PoetryProse
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

    private function podcast(array $overrides = []): Podcast
    {
        return Podcast::query()->create([
            'title' => 'Test Podcast '.uniqid(),
            'slug' => 'test-podcast-'.uniqid(),
            'status' => PodcastStatus::Published,
            ...$overrides,
        ]);
    }

    private function episode(Podcast $podcast, array $overrides = []): PodcastEpisode
    {
        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Test Episode '.uniqid(),
            'slug' => 'test-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
            'publish_date' => now(),
            ...$overrides,
        ]);
    }

    private function submission(array $overrides = []): ResourceSubmission
    {
        return ResourceSubmission::query()->create([
            'name' => 'A Submitter',
            'email' => 'submitter-'.uniqid().'@example.com',
            'category' => 'Testimony',
            'message' => 'A message body.',
            'status' => ResourceSubmissionStatus::Approved,
            'slug' => 'a-submission-'.uniqid(),
            ...$overrides,
        ]);
    }
}
