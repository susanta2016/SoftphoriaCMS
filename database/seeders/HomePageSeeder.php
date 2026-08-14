<?php

namespace Database\Seeders;

use App\Actions\Page\CreatePageAction;
use App\Actions\Page\UpdatePageAction;
use App\Enums\ModuleKey;
use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the "home" CMS Page (WEB-001's Home_page_layout_V4.1.1.png) through
 * the same CreatePageAction/UpdatePageAction the Pages admin UI uses, so it
 * behaves exactly like an admin-authored page — sections, revision
 * snapshot, SEO — rather than a second, parallel content path. Idempotent:
 * re-running it updates the existing "home" page instead of duplicating it.
 */
class HomePageSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        if (! $actor) {
            return;
        }

        $bannerId = Media::query()->where('alt_text', 'Home page hero banner')->value('id');

        $data = [
            'title' => 'Home',
            'slug' => 'home',
            'template' => PageTemplate::Custom->value,
            'status' => PageStatus::Published->value,
            'summary' => 'Music. Writing. Reflection. Thinking. Community. A space to explore ideas, discover music, and connect with what truly matters.',
            'publish_at' => now(),
            'sections' => [
                [
                    'section_type' => PageSectionType::Hero->value,
                    'title' => 'Homepage Hero',
                    'is_enabled' => true,
                    'content_json' => [
                        'heading' => 'Light is our nature. Love is our purpose.',
                        'subheading' => "Music. Writing. Reflection. Thinking. Community.\n\nA space to explore ideas, discover music, and connect with what truly matters.",
                        'media_id' => $bannerId,
                        'cta_label' => 'Explore Music',
                        'cta_url' => '#',
                        'secondary_cta_label' => 'Read Writing',
                        'secondary_cta_url' => '#',
                        'tertiary_label' => 'Watch Introduction',
                        'tertiary_url' => '#',
                    ],
                ],
                [
                    'section_type' => PageSectionType::FeaturedContent->value,
                    'title' => 'Join Our Community',
                    'is_enabled' => true,
                    'content_json' => [
                        'module_key' => ModuleKey::Community->value,
                    ],
                ],
            ],
            'seo' => [
                'meta_title' => 'All The Things Light — Home',
                'meta_description' => 'Music. Writing. Reflection. Thinking. Community. A space to explore ideas, discover music, and connect with what truly matters.',
            ],
        ];

        $page = Page::query()->where('slug', 'home')->first();

        if ($page) {
            app(UpdatePageAction::class)->handle($page, $data, $actor);
        } else {
            app(CreatePageAction::class)->handle($data, $actor);
        }
    }
}
