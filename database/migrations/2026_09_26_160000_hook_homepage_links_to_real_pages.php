<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Points the homepage's leftover placeholder / in-page links at the real
 * pages that now exist:
 *
 * - Hero "Our Services" and closing CTA "View Our Services": /#services → /services
 * - Featured Portfolio "View All Projects": # → /portfolio
 * - Technologies "View All Technologies": # → /services#technologies
 * - "Portfolio" menu items: /#portfolio → /portfolio
 *
 * A field is only changed while it still holds the old placeholder value,
 * so a link an admin already customised is left alone. Plain query-builder
 * code on purpose.
 */
return new class extends Migration
{
    /**
     * section_type [+ gallery display] => [content_json key => [old, new]]
     */
    private const CHANGES = [
        'hero' => ['secondary_cta_url' => ['/#services', '/services']],
        'cta' => ['secondary_cta_url' => ['/#services', '/services']],
        'portfolio' => ['link_url' => ['#', '/portfolio']],
        'gallery:tech_groups' => ['link_url' => ['#', '/services#technologies']],
    ];

    public function up(): void
    {
        $this->apply(false);
        DB::table('menu_items')->where('url', '/#portfolio')->update(['url' => '/portfolio']);
    }

    public function down(): void
    {
        $this->apply(true);
        DB::table('menu_items')->where('url', '/portfolio')->update(['url' => '/#portfolio']);
    }

    private function apply(bool $reverse): void
    {
        $sections = DB::table('page_sections')
            ->join('pages', 'pages.id', '=', 'page_sections.page_id')
            ->where('pages.slug', 'home')
            ->whereIn('page_sections.section_type', ['hero', 'cta', 'portfolio', 'gallery'])
            ->select('page_sections.*')
            ->get();

        foreach ($sections as $section) {
            $content = json_decode($section->content_json ?? '[]', true) ?: [];
            $key = $section->section_type === 'gallery'
                ? 'gallery:'.($content['display'] ?? '')
                : $section->section_type;
            $changed = false;

            foreach (self::CHANGES[$key] ?? [] as $field => [$old, $new]) {
                [$from, $to] = $reverse ? [$new, $old] : [$old, $new];

                if (($content[$field] ?? null) === $from) {
                    $content[$field] = $to;
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('page_sections')->where('id', $section->id)->update(['content_json' => json_encode($content)]);
            }
        }
    }
};
