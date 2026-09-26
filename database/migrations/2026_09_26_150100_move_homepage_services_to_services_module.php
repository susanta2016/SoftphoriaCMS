<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Services become a real module:
 *
 * - The homepage "Our Services" block (a Gallery section with
 *   display=services and six typed-in cards) becomes a Services section
 *   (section_type=services) that shows featured services live. Each card
 *   becomes a `services` row — keeping the card's own title, description
 *   and icon — with starter detail-page copy from
 *   database/seeders/data/services.php where the title matches.
 * - Menu links to "/#services" now go to /services, or straight to the
 *   matching service page for the footer's per-service links.
 *
 * Only runs its content part when the services table is still empty, so it
 * never duplicates services an admin created by hand. Plain query-builder
 * code on purpose.
 */
return new class extends Migration
{
    /** Footer link label => service slug (labels are shorter than titles). */
    private const MENU_SLUGS = [
        'Web Development' => 'web-development',
        'Custom Software' => 'custom-software',
        'E-Commerce' => 'e-commerce-solutions',
        'E-Commerce Solutions' => 'e-commerce-solutions',
        'Cloud & DevOps' => 'cloud-devops',
        'API & Integrations' => 'api-system-integrations',
        'API & System Integrations' => 'api-system-integrations',
        'CMS & Content Platforms' => 'cms-content-platforms',
    ];

    public function up(): void
    {
        if (DB::table('services')->exists()) {
            return;
        }

        $starter = collect(require database_path('seeders/data/services.php'))->keyBy('title');
        $now = now();
        $slugs = [];

        $sections = $this->homeSections('gallery')
            ->filter(fn (object $section): bool => (json_decode($section->content_json ?? '[]', true)['display'] ?? null) === 'services');

        foreach ($sections as $section) {
            $content = json_decode($section->content_json ?? '[]', true) ?: [];

            foreach (array_values($content['gallery_items'] ?? []) as $index => $item) {
                if (blank($item['title'] ?? null)) {
                    continue;
                }

                $copy = $starter->get($item['title'], []);
                $slug = $copy['slug'] ?? Str::slug($item['title']);

                if (isset($slugs[$slug])) {
                    continue;
                }
                $slugs[$slug] = true;

                DB::table('services')->insert([
                    'title' => $item['title'],
                    'slug' => $slug,
                    'icon' => ($item['icon'] ?? null) ?: ($copy['icon'] ?? null),
                    'tagline' => $copy['tagline'] ?? null,
                    'summary' => ($item['description'] ?? null) ?: ($copy['summary'] ?? null),
                    'body' => $copy['body'] ?? null,
                    'cover_media_id' => null,
                    'highlights' => json_encode($copy['highlights'] ?? []),
                    'technologies' => json_encode($copy['technologies'] ?? []),
                    'faqs' => json_encode($copy['faqs'] ?? []),
                    'is_featured' => true,
                    'is_published' => true,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            unset($content['display'], $content['gallery_items']);
            $content['limit'] = 6;
            if (blank($content['link_url'] ?? null) || ($content['link_url'] ?? null) === '#') {
                $content['link_url'] = '/services';
            }

            DB::table('page_sections')->where('id', $section->id)->update([
                'section_type' => 'services',
                'content_json' => json_encode($content),
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('menu_items')->where('url', '/#services')->get() as $item) {
            $slug = self::MENU_SLUGS[$item->label] ?? null;

            DB::table('menu_items')->where('id', $item->id)->update([
                'url' => $slug && isset($slugs[$slug]) ? "/services/{$slug}" : '/services',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', '/services')->orWhere('url', 'like', '/services/%')->update(['url' => '/#services']);

        $services = DB::table('services')->orderBy('sort_order')->get();

        foreach ($this->homeSections('services') as $section) {
            $content = json_decode($section->content_json ?? '[]', true) ?: [];
            unset($content['limit']);
            $content['display'] = 'services';
            $content['gallery_items'] = $services->map(fn (object $service): array => [
                'media_id' => null,
                'title' => $service->title,
                'description' => $service->summary,
                'icon' => $service->icon,
                'url' => null,
            ])->all();

            DB::table('page_sections')->where('id', $section->id)->update([
                'section_type' => 'gallery',
                'content_json' => json_encode($content),
            ]);
        }

        DB::table('services')->delete();
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
