<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Work" becomes "Portfolio":
 *
 * - every menu item labelled "Work" is relabelled "Portfolio", and a
 *   "/#work" link now points at "/#portfolio";
 * - the homepage's "Featured Work" block (a Gallery section with
 *   display=projects whose cards were typed into the section itself)
 *   becomes a Featured Portfolio section (section_type=portfolio), which
 *   reads featured rows from the new portfolio_items table instead. Its
 *   existing cards are copied into portfolio_items as featured + published,
 *   keeping their images/links, so the homepage looks the same after
 *   deploy.
 *
 * Plain query-builder code on purpose, so later model changes can't break it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')->where('label', 'Work')->update(['label' => 'Portfolio']);
        DB::table('menu_items')->where('url', '/#work')->update(['url' => '/#portfolio']);

        foreach ($this->homeSections('gallery') as $section) {
            $content = json_decode($section->content_json ?? '[]', true) ?: [];

            if (($content['display'] ?? null) !== 'projects') {
                continue;
            }

            $now = now();

            foreach (array_values($content['gallery_items'] ?? []) as $index => $item) {
                if (blank($item['title'] ?? null)) {
                    continue;
                }

                $url = $item['url'] ?? null;

                DB::table('portfolio_items')->insert([
                    'title' => $item['title'],
                    'summary' => $item['description'] ?? null,
                    'cover_media_id' => $item['media_id'] ?? null,
                    'icon' => $item['icon'] ?? null,
                    'link_url' => filled($url) && $url !== '#' ? $url : null,
                    'is_featured' => true,
                    'is_published' => true,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            unset($content['display'], $content['gallery_items']);
            $content['limit'] = 6;
            $content['anchor'] = ($content['anchor'] ?? null) === 'work' ? 'portfolio' : ($content['anchor'] ?? 'portfolio');
            $content['eyebrow'] = ($content['eyebrow'] ?? null) === 'Featured Work' ? 'Featured Portfolio' : ($content['eyebrow'] ?? null);

            DB::table('page_sections')->where('id', $section->id)->update([
                'section_type' => 'portfolio',
                'title' => $section->title === 'Featured Work' ? 'Featured Portfolio' : $section->title,
                'content_json' => json_encode($content),
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menu_items')->where('label', 'Portfolio')->update(['label' => 'Work']);
        DB::table('menu_items')->where('url', '/#portfolio')->update(['url' => '/#work']);

        $items = DB::table('portfolio_items')->where('is_featured', true)->orderBy('sort_order')->orderBy('id')->get();

        foreach ($this->homeSections('portfolio') as $section) {
            $content = json_decode($section->content_json ?? '[]', true) ?: [];
            unset($content['limit']);

            $content['display'] = 'projects';
            $content['anchor'] = ($content['anchor'] ?? null) === 'portfolio' ? 'work' : ($content['anchor'] ?? null);
            $content['eyebrow'] = ($content['eyebrow'] ?? null) === 'Featured Portfolio' ? 'Featured Work' : ($content['eyebrow'] ?? null);
            $content['gallery_items'] = $items->map(fn (object $item): array => [
                'media_id' => $item->cover_media_id,
                'title' => $item->title,
                'description' => $item->summary,
                'url' => $item->link_url,
                'icon' => $item->icon,
            ])->all();

            DB::table('page_sections')->where('id', $section->id)->update([
                'section_type' => 'gallery',
                'title' => $section->title === 'Featured Portfolio' ? 'Featured Work' : $section->title,
                'content_json' => json_encode($content),
            ]);
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function homeSections(string $type): Collection
    {
        return DB::table('page_sections')
            ->join('pages', 'pages.id', '=', 'page_sections.page_id')
            ->where('pages.slug', 'home')
            ->where('page_sections.section_type', $type)
            ->select('page_sections.*')
            ->get();
    }
};
