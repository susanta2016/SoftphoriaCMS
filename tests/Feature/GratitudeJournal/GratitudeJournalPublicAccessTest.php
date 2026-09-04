<?php

namespace Tests\Feature\GratitudeJournal;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gratitude Journal audit §5: a Journal entry has no public standalone
 * detail page — its only public surface is the homepage feed
 * (GratitudeJournalVisibilityTest). LightPostController::show() must reject
 * any source = journal row even when public, while a genuine registration
 * Light Post keeps working through that exact same route exactly as before
 * (regression-checked here and in the pre-existing, untouched
 * tests/Feature/Community/LightPostShowTest.php).
 */
class GratitudeJournalPublicAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_journal_entry_cannot_be_accessed_through_the_public_light_post_detail_route(): void
    {
        $user = User::factory()->create();
        $entry = (new CreateGratitudeJournalEntryAction)->handle($user, 'A public journal entry.', true);

        $response = $this->get(route('light-posts.show', $entry));

        $response->assertNotFound();
    }

    public function test_a_public_journal_entry_is_absent_from_the_sitemap(): void
    {
        $user = User::factory()->create();
        $entry = (new CreateGratitudeJournalEntryAction)->handle($user, 'A public journal entry for sitemap check.', true);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('light-posts.show', $entry), false);
    }

    public function test_a_registration_light_post_still_works_through_the_public_detail_route(): void
    {
        $user = User::factory()->create(['name' => 'Registration Poster']);
        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'source' => LightPostSource::Registration,
            'content' => 'A genuine registration light post.',
            'is_public' => true,
        ]);

        $response = $this->get(route('light-posts.show', $post));

        $response->assertOk();
        $response->assertSee('A genuine registration light post.');
        $response->assertSee('Registration Poster');
    }
}
