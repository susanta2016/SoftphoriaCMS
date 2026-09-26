<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Blog replaces the homepage's placeholder "Latest Insights" gallery:
 *
 * - the home page's Gallery section with display=articles (three typed-in
 *   sample cards linking to "#") becomes a Latest Blog Posts section
 *   (section_type=blog_posts), which shows real published posts and hides
 *   itself until there are some. Its heading/eyebrow/background/anchor
 *   are kept; the header link now points at /blog.
 * - menu items linking to "/#insights" now link to "/blog".
 *
 * Plain query-builder code on purpose, so later model changes can't break it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')->where('url', '/#insights')->update(['url' => '/blog']);

        foreach ($this->homeSections('gallery') as $section) {
            $content = json_decode($section->content_json ?? '[]', true) ?: [];

            if (($content['display'] ?? null) !== 'articles') {
                continue;
            }

            unset($content['display'], $content['gallery_items'], $content['item_link_label']);
            $content['limit'] = 3;
            if (blank($content['link_url'] ?? null) || ($content['link_url'] ?? null) === '#') {
                $content['link_url'] = '/blog';
            }

            DB::table('page_sections')->where('id', $section->id)->update([
                'section_type' => 'blog_posts',
                'content_json' => json_encode($content),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', '/blog')->update(['url' => '/#insights']);

        foreach ($this->homeSections('blog_posts') as $section) {
            $content = json_decode($section->content_json ?? '[]', true) ?: [];
            unset($content['limit']);
            $content['display'] = 'articles';
            $content['item_link_label'] = 'Read More';
            $content['gallery_items'] = [];

            DB::table('page_sections')->where('id', $section->id)->update([
                'section_type' => 'gallery',
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
