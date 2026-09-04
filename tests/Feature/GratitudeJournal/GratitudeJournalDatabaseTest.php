<?php

namespace Tests\Feature\GratitudeJournal;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Actions\Registration\Concerns\CreatesLightPostOnRegistration;
use App\Enums\GratitudeJournalVisibility;
use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Gratitude Journal audit's approved data-model decision: reuse
 * light_posts with a `source` discriminator (App\Enums\LightPostSource)
 * rather than a second table — see database/migrations/
 * 2026_09_04_120000_add_source_to_light_posts_table.php.
 */
class GratitudeJournalDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_gratitude_journal_entry_is_created_with_source_journal(): void
    {
        $user = User::factory()->create();

        $entry = (new CreateGratitudeJournalEntryAction)->handle($user, 'Grateful for quiet mornings.');

        $this->assertSame(LightPostSource::Journal, $entry->source);
        $this->assertSame($user->id, $entry->user_id);
    }

    public function test_a_registration_light_post_is_still_created_with_source_registration(): void
    {
        $user = User::factory()->create();

        $trait = new class
        {
            use CreatesLightPostOnRegistration;

            public function create(User $user, array $data): void
            {
                $this->createLightPostIfRequested($user, $data);
            }
        };

        $trait->create($user, ['light_post_action' => 'share', 'light_message' => 'Grateful for this community.']);

        $post = $user->lightPosts()->firstOrFail();
        $this->assertSame(LightPostSource::Registration, $post->source);
    }

    /**
     * Simulates a pre-migration row: the migration adds `source` with a
     * database-level default of 'registration', so every row that existed
     * before the column was added — and any insert that doesn't specify a
     * source at all — is correctly backfilled/defaulted without any
     * application code having to set it explicitly.
     */
    public function test_a_light_post_row_created_without_an_explicit_source_defaults_to_registration(): void
    {
        $user = User::factory()->create();

        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'content' => 'A pre-existing post with no source specified.',
            'visibility' => GratitudeJournalVisibility::Public,
        ]);

        $this->assertSame(LightPostSource::Registration, $post->fresh()->source);
    }
}
