<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrects existing portfolio copy (content only — no schema change):
 *
 * - the B2B E-Commerce Platform description, only while it still holds the
 *   exact original wording;
 * - technology tags: a tag typed as one comma-separated string ("NodeJs,
 *   AngularJs, MySQL, AWS") is split into separate tags, and informal
 *   spellings get their professional names (Node.js, Angular, MySQL, AWS).
 *
 * Plain query-builder code on purpose, so later model changes can't break it.
 */
return new class extends Migration
{
    private const OLD_B2B_SUMMARY = 'A large scale e-commerce platform with complex product management, pricing and ERP integration.';

    private const NEW_B2B_SUMMARY = 'A large-scale B2B e-commerce platform with complex product management, pricing, and ERP integration.';

    /** Lower-cased, space-less spelling => professional name. */
    private const TECHNOLOGY_NAMES = [
        'nodejs' => 'Node.js',
        'node.js' => 'Node.js',
        'node' => 'Node.js',
        'angularjs' => 'Angular',
        'angular' => 'Angular',
        'mysql' => 'MySQL',
        'aws' => 'AWS',
    ];

    public function up(): void
    {
        DB::table('portfolio_items')->where('summary', self::OLD_B2B_SUMMARY)->update(['summary' => self::NEW_B2B_SUMMARY]);

        foreach (DB::table('portfolio_items')->whereNotNull('technologies')->get(['id', 'technologies']) as $item) {
            $tags = json_decode($item->technologies, true);

            if (! is_array($tags)) {
                continue;
            }

            $normalised = collect($tags)
                ->flatMap(fn ($tag): array => explode(',', (string) $tag))
                ->map(fn (string $tag): string => trim($tag))
                ->filter()
                ->map(fn (string $tag): string => self::TECHNOLOGY_NAMES[strtolower(str_replace(' ', '', $tag))] ?? $tag)
                ->unique()
                ->values()
                ->all();

            if ($normalised !== $tags) {
                DB::table('portfolio_items')->where('id', $item->id)->update(['technologies' => json_encode($normalised)]);
            }
        }
    }

    public function down(): void
    {
        // Only the description is restored; split/renamed technology tags
        // are the correct form and are left as they are.
        DB::table('portfolio_items')->where('summary', self::NEW_B2B_SUMMARY)->update(['summary' => self::OLD_B2B_SUMMARY]);
    }
};
