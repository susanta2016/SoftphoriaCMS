<?php

namespace Tests\Feature\GratitudeJournal;

use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * DeleteExpiredGratitudeJournalEntriesCommand — deletes Gratitude Journal
 * entries (source = journal, Public and Private alike) older than
 * config('features.gratitude_journal_retention_months')
 * (GRATITUDE_JOURNAL_RETENTION_MONTHS, default 6, ENV-only). Registration
 * Light Posts (source = registration) must never be touched by this
 * command regardless of age.
 */
class GratitudeJournalRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_retention_period_is_six_months(): void
    {
        $this->assertSame(6, config('features.gratitude_journal_retention_months'));
    }

    public function test_a_configured_env_value_changes_the_retention_period(): void
    {
        config(['features.gratitude_journal_retention_months' => 2]);
        $user = User::factory()->create();

        $expired = $this->journalEntry($user, 'Expired under the 2-month policy.', true, now()->subMonthsNoOverflow(3));
        $retained = $this->journalEntry($user, 'Retained under the 2-month policy.', true, now()->subMonthsNoOverflow(1));

        $this->artisan('gratitude-journal:delete-expired')->assertExitCode(0);

        $this->assertNull(LightPost::query()->find($expired->id));
        $this->assertNotNull(LightPost::query()->find($retained->id));
    }

    public function test_an_expired_public_journal_entry_is_deleted(): void
    {
        $user = User::factory()->create();
        $entry = $this->journalEntry($user, 'Expired public entry.', true, now()->subMonthsNoOverflow(7));

        $this->artisan('gratitude-journal:delete-expired')->assertExitCode(0);

        $this->assertNull(LightPost::query()->find($entry->id));
    }

    public function test_an_expired_private_journal_entry_is_deleted(): void
    {
        $user = User::factory()->create();
        $entry = $this->journalEntry($user, 'Expired private entry.', false, now()->subMonthsNoOverflow(7));

        $this->artisan('gratitude-journal:delete-expired')->assertExitCode(0);

        $this->assertNull(LightPost::query()->find($entry->id));
    }

    public function test_a_non_expired_journal_entry_is_retained(): void
    {
        $user = User::factory()->create();
        $entry = $this->journalEntry($user, 'Still within the retention window.', true, now()->subMonthsNoOverflow(5));

        $this->artisan('gratitude-journal:delete-expired')->assertExitCode(0);

        $this->assertNotNull(LightPost::query()->find($entry->id));
    }

    /**
     * The one hard boundary this whole feature depends on: retention only
     * ever applies to source = journal. An old registration-time Light
     * Post — including one far older than the retention window — must
     * survive every run of this command untouched.
     */
    public function test_a_registration_light_post_is_never_deleted_regardless_of_age(): void
    {
        $user = User::factory()->create();
        $post = LightPost::query()->create([
            'user_id' => $user->id,
            'source' => LightPostSource::Registration,
            'content' => 'A very old registration light post.',
            'is_public' => true,
        ]);
        $post->forceFill(['created_at' => now()->subYears(5)])->save();

        $this->artisan('gratitude-journal:delete-expired')->assertExitCode(0);

        $this->assertNotNull(LightPost::query()->find($post->id));
    }

    public function test_running_cleanup_twice_is_safe_and_idempotent(): void
    {
        $user = User::factory()->create();
        $expired = $this->journalEntry($user, 'Expired, deleted on the first run.', true, now()->subMonthsNoOverflow(7));
        $retained = $this->journalEntry($user, 'Retained across both runs.', true, now()->subMonthsNoOverflow(1));

        $this->artisan('gratitude-journal:delete-expired')->assertExitCode(0);
        $this->assertNull(LightPost::query()->find($expired->id));
        $this->assertNotNull(LightPost::query()->find($retained->id));

        // A second run finds nothing new to delete and does not error or
        // touch the still-valid entry.
        $this->artisan('gratitude-journal:delete-expired')->assertExitCode(0);
        $this->assertNotNull(LightPost::query()->find($retained->id));
        $this->assertSame(1, LightPost::query()->count());
    }

    private function journalEntry(User $user, string $content, bool $isPublic, Carbon $createdAt): LightPost
    {
        $entry = LightPost::query()->create([
            'user_id' => $user->id,
            'source' => LightPostSource::Journal,
            'content' => $content,
            'is_public' => $isPublic,
        ]);

        $entry->forceFill(['created_at' => $createdAt])->save();

        return $entry->fresh();
    }
}
