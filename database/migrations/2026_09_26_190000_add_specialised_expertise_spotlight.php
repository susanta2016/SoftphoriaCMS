<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the "Specialised Expertise" spotlight (AI Development, Cloud &
 * DevOps, Digital Marketing) to existing sites:
 *
 * - on a site that already has services, creates the AI Development and
 *   Digital Marketing services from database/seeders/data/services.php if
 *   their slugs don't exist yet (published, not in the homepage's main
 *   services grid). A fresh install is left to HomePageSeeder;
 * - inserts the spotlight Services section (database/seeders/data/
 *   specialised-expertise-section.php) into the home page after its
 *   "Why Softphoria" block, and into the About page after "Our Values" —
 *   only where no spotlight section exists yet and the anchor block is
 *   found, so admin-arranged pages are never reshuffled blindly.
 *
 * Plain query-builder code on purpose.
 */
return new class extends Migration
{
    private const NEW_SERVICES = ['ai-development', 'digital-marketing'];

    public function up(): void
    {
        $now = now();
        $order = (int) DB::table('services')->max('sort_order');

        // A fresh install has no services yet: HomePageSeeder creates all of
        // them (only into an empty table), so adding two here would stop it.
        $existingSite = DB::table('services')->exists();

        foreach ($existingSite ? require database_path('seeders/data/services.php') : [] as $service) {
            if (! in_array($service['slug'], self::NEW_SERVICES, true) || DB::table('services')->where('slug', $service['slug'])->exists()) {
                continue;
            }

            DB::table('services')->insert([
                'title' => $service['title'],
                'slug' => $service['slug'],
                'icon' => $service['icon'] ?? null,
                'tagline' => $service['tagline'] ?? null,
                'summary' => $service['summary'] ?? null,
                'body' => $service['body'] ?? null,
                'highlights' => json_encode($service['highlights'] ?? []),
                'technologies' => json_encode($service['technologies'] ?? []),
                'faqs' => json_encode($service['faqs'] ?? []),
                'is_featured' => (bool) ($service['is_featured'] ?? false),
                'is_published' => true,
                'sort_order' => ++$order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $section = require database_path('seeders/data/specialised-expertise-section.php');

        $this->insertAfter('home', fn (object $s, array $c): bool => $s->section_type === 'gallery' && ($c['display'] ?? null) === 'features', $section);
        $this->insertAfter('about', fn (object $s, array $c): bool => $s->section_type === 'gallery' && ($c['display'] ?? null) === 'features', $section);
    }

    public function down(): void
    {
        DB::table('page_sections')
            ->where('section_type', 'services')
            ->where('content_json', 'like', '%"style":"spotlight"%')
            ->where('title', 'Specialised Expertise')
            ->delete();
    }

    /**
     * @param  callable(object, array<string, mixed>): bool  $isAnchor
     * @param  array<string, mixed>  $section
     */
    private function insertAfter(string $slug, callable $isAnchor, array $section): void
    {
        $pageId = DB::table('pages')->where('slug', $slug)->value('id');

        if (! $pageId) {
            return;
        }

        $sections = DB::table('page_sections')->where('page_id', $pageId)->orderBy('sort_order')->get();

        $hasSpotlight = $sections->contains(fn (object $s): bool => $s->section_type === 'services'
            && (json_decode($s->content_json ?? '[]', true)['style'] ?? null) === 'spotlight');
        $anchor = $sections->first(fn (object $s): bool => $isAnchor($s, json_decode($s->content_json ?? '[]', true) ?: []));

        if ($hasSpotlight || ! $anchor) {
            return;
        }

        DB::table('page_sections')
            ->where('page_id', $pageId)
            ->where('sort_order', '>', $anchor->sort_order)
            ->increment('sort_order');

        DB::table('page_sections')->insert([
            'page_id' => $pageId,
            'section_type' => $section['section_type'],
            'title' => $section['title'],
            'sort_order' => $anchor->sort_order + 1,
            'is_enabled' => true,
            'content_json' => json_encode($section['content_json']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
