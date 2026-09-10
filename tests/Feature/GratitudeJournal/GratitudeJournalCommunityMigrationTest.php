<?php

namespace Tests\Feature\GratitudeJournal;

use App\Enums\LightPostSource;
use App\Models\LightPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * database/migrations/2026_09_10_090000_migrate_community_gratitude_journal_entries_to_public.php
 * — the one-way data migration that folds every legacy
 * visibility = 'community' Journal row into 'public' (Gratitude Journal
 * simplified from three states to two, 2026-09-10).
 *
 * RefreshDatabase already runs every migration, including this one, before
 * each test — at that point there are no 'community' rows left to convert
 * (the enum itself no longer allows creating one through the application).
 * To exercise the migration's own conversion logic against a genuine
 * pre-migration row, this test inserts a raw 'community' value directly via
 * the query builder (bypassing Eloquent's GratitudeJournalVisibility cast,
 * which would reject it) to simulate a legacy row, then re-runs the
 * migration's up() directly — safe because the migration is a plain,
 * idempotent UPDATE, not a schema change.
 */
class GratitudeJournalCommunityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_legacy_community_journal_row_is_converted_to_public(): void
    {
        $user = User::factory()->create();

        $legacyId = DB::table('light_posts')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'source' => LightPostSource::Journal->value,
            'content' => 'A legacy community journal row.',
            'visibility' => 'community',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_10_090000_migrate_community_gratitude_journal_entries_to_public.php');
        $migration->up();

        $this->assertSame('public', DB::table('light_posts')->find($legacyId)->visibility);
        $this->assertSame('public', LightPost::query()->find($legacyId)->visibility->value);
    }

    /**
     * The migration scopes to source = journal — a defensive guard, since a
     * registration-time Light Post is never created as 'community' in the
     * first place (CreatesLightPostOnRegistration always sets 'public').
     */
    public function test_a_registration_light_post_is_never_touched_even_if_it_somehow_had_a_community_value(): void
    {
        $user = User::factory()->create();

        $legacyId = DB::table('light_posts')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'source' => LightPostSource::Registration->value,
            'content' => 'A registration row that should never carry this value.',
            'visibility' => 'community',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_10_090000_migrate_community_gratitude_journal_entries_to_public.php');
        $migration->up();

        $this->assertSame('community', DB::table('light_posts')->find($legacyId)->visibility);
    }
}
