<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrects the headline stats on hero sections (Home and About): "100+
 * Projects Delivered" overstated what Susanta Bera personally delivered
 * (~15–20+); the 100+ figure is broader career experience across several
 * companies. Content only — no schema change.
 *
 * Only stat rows that still hold the exact seeded wording are rewritten,
 * so anything an admin has since edited in Pages is left alone.
 *
 * Plain query-builder code on purpose, so later model changes can't break it.
 */
return new class extends Migration
{
    /** @var array<int, array{from: array{value: string, label: string}, to: array{value: string, label: string}}> */
    private const REPLACEMENTS = [
        ['from' => ['value' => '20+', 'label' => 'Years Experience'], 'to' => ['value' => '20+', 'label' => 'Years of Experience']],
        ['from' => ['value' => '100+', 'label' => 'Projects Delivered'], 'to' => ['value' => '15–20+', 'label' => 'Projects Personally Delivered']],
    ];

    public function up(): void
    {
        $this->swap('from', 'to');
    }

    public function down(): void
    {
        $this->swap('to', 'from');
    }

    private function swap(string $old, string $new): void
    {
        foreach (DB::table('page_sections')->where('section_type', 'hero')->get(['id', 'content_json']) as $section) {
            $content = json_decode($section->content_json ?? '[]', true) ?: [];

            if (! is_array($content['stats'] ?? null)) {
                continue;
            }

            $changed = false;

            foreach ($content['stats'] as $index => $stat) {
                foreach (self::REPLACEMENTS as $replacement) {
                    if (($stat['value'] ?? null) === $replacement[$old]['value'] && ($stat['label'] ?? null) === $replacement[$old]['label']) {
                        $content['stats'][$index] = [...$stat, ...$replacement[$new]];
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                DB::table('page_sections')->where('id', $section->id)->update([
                    'content_json' => json_encode($content),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
