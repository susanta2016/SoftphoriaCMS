<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Appends Shopify, Claude Code, Replit and Lovable to the homepage's
 * "Trusted Technologies" logo strip (the home page's Gallery section with
 * display=logos) on databases that already have it, without re-running
 * HomePageSeeder (which would reset admin-edited homepage copy). A logo
 * already in the strip — matched by its icon key — is never added twice,
 * and every other item is left as the admin arranged it.
 */
return new class extends Migration
{
    private const array ITEMS = [
        ['title' => 'Shopify', 'icon' => 'shopify'],
        ['title' => 'Claude Code', 'icon' => 'claude'],
        ['title' => 'Replit', 'icon' => 'replit'],
        ['title' => 'Lovable', 'icon' => 'lovable'],
    ];

    public function up(): void
    {
        foreach ($this->logoSections() as $section) {
            $content = json_decode($section->content_json ?? '[]', true) ?: [];
            $items = $content['gallery_items'] ?? [];
            $existingIcons = array_column($items, 'icon');

            foreach (self::ITEMS as $item) {
                if (! in_array($item['icon'], $existingIcons, true)) {
                    $items[] = ['media_id' => null, 'url' => null, ...$item];
                }
            }

            $content['gallery_items'] = $items;

            DB::table('page_sections')->where('id', $section->id)->update([
                'content_json' => json_encode($content),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $icons = array_column(self::ITEMS, 'icon');

        foreach ($this->logoSections() as $section) {
            $content = json_decode($section->content_json ?? '[]', true) ?: [];
            $content['gallery_items'] = array_values(array_filter(
                $content['gallery_items'] ?? [],
                fn (array $item): bool => ! in_array($item['icon'] ?? null, $icons, true),
            ));

            DB::table('page_sections')->where('id', $section->id)->update(['content_json' => json_encode($content)]);
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function logoSections(): Collection
    {
        return DB::table('page_sections')
            ->join('pages', 'pages.id', '=', 'page_sections.page_id')
            ->where('pages.slug', 'home')
            ->where('page_sections.section_type', 'gallery')
            ->select('page_sections.*')
            ->get()
            ->filter(fn (object $section): bool => (json_decode($section->content_json ?? '[]', true)['display'] ?? null) === 'logos');
    }
};
