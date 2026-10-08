<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DatabaseSeeder runs WithoutModelEvents, so anything a model normally
 * fills in a creating hook (slugs, defaults) must be set by the seeder
 * itself. This catches a seeder that only works with events on.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_database_seeder_runs_on_a_fresh_database(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('portfolio_items', 3);
        $this->assertDatabaseMissing('portfolio_items', ['slug' => null]);
    }
}
