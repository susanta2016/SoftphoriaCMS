<?php

namespace Tests\Feature;

use App\Filament\Support\Seo\SeoFields;
use App\Models\SeoMetadata;
use App\Models\ToolCategory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * Seeders write SEO straight to the database, skipping the admin form's
 * validation (Meta Title max 60, Meta Description max 160 characters). A
 * longer seeded value shows fine on the site but blocks the next admin save
 * of that record until someone shortens it. Every seeder that writes SEO is
 * run here (found by scanning database/seeders, so new seeders are covered
 * too) and every stored value is checked against the same limits as the form.
 */
class SeededSeoLimitsTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_08_180000_shorten_seeded_seo_metadata.php';

    public function test_every_seeded_seo_title_and_description_fits_the_admin_limits(): void
    {
        $this->admin();
        ToolCategory::query()->firstOrCreate(['slug' => 'image-media'], ['name' => 'Image & Media']);

        $seeders = $this->seedersThatWriteSeo();
        $this->assertNotEmpty($seeders);
        foreach ($seeders as $seeder) {
            $this->seed($seeder);
        }

        $records = SeoMetadata::query()->get();
        $this->assertGreaterThan(count($seeders), $records->count(), 'the seeders created SEO records');

        $tooLong = $records
            ->flatMap(fn (SeoMetadata $seo): array => array_values(array_filter([
                mb_strlen((string) $seo->meta_title) > SeoFields::META_TITLE_MAX
                    ? sprintf('title (%d): %s', mb_strlen($seo->meta_title), $seo->meta_title) : null,
                mb_strlen((string) $seo->meta_description) > SeoFields::META_DESCRIPTION_MAX
                    ? sprintf('description (%d): %s', mb_strlen($seo->meta_description), $seo->meta_description) : null,
            ])))
            ->values()
            ->all();

        $this->assertSame([], $tooLong, sprintf(
            'Shorten these seeded SEO values (titles: %d, descriptions: %d characters).',
            SeoFields::META_TITLE_MAX,
            SeoFields::META_DESCRIPTION_MAX,
        ));
    }

    public function test_the_data_migration_changes_only_values_still_matching_the_old_seeded_text(): void
    {
        $row = fn (int $id, string $title, string $description) => DB::table('seo_metadata')->insert([
            'seoable_type' => 'App\\Models\\Page', 'seoable_id' => $id, 'meta_title' => $title, 'meta_description' => $description,
        ]);
        $row(1, 'Instagram Reels Safe Zone (1080×1920): Measured Margins | Softphoria', 'Edited description');
        $row(2, 'Edited by an admin', 'See where Instagram Reels and YouTube Shorts buttons, captions and top bars cover your vertical video, with suggested fixes. Runs in your browser; nothing is uploaded.');

        $migration = require database_path(self::MIGRATION);
        $migration->up();
        $migration->up(); // running it again changes nothing

        $rows = DB::table('seo_metadata')->orderBy('seoable_id')->get(['meta_title', 'meta_description']);
        $this->assertSame('Instagram Reels Safe Zone (1080×1920): Measured Margins', $rows[0]->meta_title);
        $this->assertSame('Edited description', $rows[0]->meta_description);
        $this->assertSame('Edited by an admin', $rows[1]->meta_title);
        $this->assertLessThanOrEqual(SeoFields::META_DESCRIPTION_MAX, mb_strlen($rows[1]->meta_description));
    }

    /** @return list<class-string> */
    private function seedersThatWriteSeo(): array
    {
        $classes = [];
        foreach (glob(database_path('seeders/*.php')) as $file) {
            $class = 'Database\\Seeders\\'.basename($file, '.php');
            if ($class !== DatabaseSeeder::class && str_contains((string) file_get_contents($file), 'meta_title')) {
                $classes[] = $class;
            }
        }
        sort($classes);

        return $classes;
    }
}
