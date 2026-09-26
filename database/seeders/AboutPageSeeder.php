<?php

namespace Database\Seeders;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Actions\Page\CreatePageAction;
use App\Actions\Page\UpdatePageAction;
use App\Enums\MediaCategory;
use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The About Us page (/about) as an editable CMS page, rebuilt from the
 * content of the original softphoria.com/about-us page: header band with
 * stats, Our Story, Our Values, the Specialised Expertise spotlight,
 * Technical Expertise, Meet the Founder and a
 * closing call to action. Every section stays editable in Admin → Pages.
 *
 * Images ship in database/seeders/assets/about and are imported into the
 * Media Library once (reused on re-runs): the founder photo from the
 * original page, and softphoria-story.jpg — an original brand illustration
 * rendered from softphoria-story.source.html (edit that file and re-render
 * to change it).
 *
 * Never overwrites an admin's work: the page is written only if it doesn't
 * exist yet or still holds placeholder content ("Long Description…" or no
 * sections). Set ABOUT_PAGE_FORCE=true to deliberately replace it.
 */
class AboutPageSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        if (! $actor) {
            return;
        }

        $page = Page::query()->where('slug', 'about')->with('sections')->first();
        $force = filter_var(env('ABOUT_PAGE_FORCE', false), FILTER_VALIDATE_BOOL);

        if ($page && ! $force && ! $this->isPlaceholder($page)) {
            $this->command?->warn('Skipped /about: it has been edited in Admin → Pages (set ABOUT_PAGE_FORCE=true to replace it).');

            return;
        }

        $founderPhoto = $this->importImage('Susanta-Bera.jpeg', 'Susanta Bera, founder of Softphoria', $actor);
        $storyImage = $this->importImage('softphoria-story.jpg', 'Illustration of a code editor surrounded by cloud, AI automation and growth dashboards', $actor);

        $data = [
            'title' => 'About Us',
            'slug' => 'about',
            'template' => PageTemplate::About->value,
            'status' => PageStatus::Published->value,
            'summary' => 'Softphoria builds powerful web experiences for businesses — founded by a full-stack developer with over 20 years of experience.',
            'publish_at' => now(),
            'sections' => $this->sections($founderPhoto, $storyImage),
            'seo' => [
                'meta_title' => 'About Softphoria — 20+ Years Building Powerful Web Experiences',
                'meta_description' => 'Meet Softphoria: a founder-led web development studio from Kolkata, India, building custom websites, software and integrations for clients worldwide.',
            ],
        ];

        $page
            ? app(UpdatePageAction::class)->handle($page, $data, $actor)
            : app(CreatePageAction::class)->handle($data, $actor);

        $this->command?->info('Published /about');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sections(?Media $founderPhoto, ?Media $storyImage): array
    {
        return [
            [
                'section_type' => PageSectionType::Hero->value,
                'title' => 'About header',
                'is_enabled' => true,
                'content_json' => [
                    'style' => 'band',
                    'eyebrow' => 'About Softphoria',
                    'heading' => 'Building powerful web experiences',
                    'heading_highlight' => 'for over 20 years.',
                    'subheading' => 'We design, build and support websites, custom software and integrations that help businesses grow — with the care and accountability of a founder-led studio.',
                    'cta_label' => "Let's Work Together",
                    'cta_url' => '/contact',
                    'secondary_cta_label' => 'Our Services',
                    'secondary_cta_url' => '/services',
                    'stats' => [
                        ['value' => '20+', 'label' => 'Years Experience'],
                        ['value' => '100+', 'label' => 'Projects Delivered'],
                        ['value' => 'Long-term', 'label' => 'Client Relationships'],
                    ],
                ],
            ],
            [
                'section_type' => PageSectionType::ImageText->value,
                'title' => 'Our Story',
                'is_enabled' => true,
                'content_json' => [
                    'style' => 'split',
                    'background' => 'white',
                    'anchor' => 'our-story',
                    'eyebrow' => 'Our Story',
                    'heading' => 'Great websites are digital tools for growth.',
                    'text' => "At Softphoria, we believe great websites are more than code — they're digital tools for growth.\n\nFounded by Susanta Bera, a veteran full-stack developer with over 20 years of experience, we build custom solutions that blend clean code, speed, and creativity.",
                    'media_id' => $storyImage?->id,
                ],
            ],
            [
                'section_type' => PageSectionType::Gallery->value,
                'title' => 'Our Values',
                'is_enabled' => true,
                'content_json' => [
                    'display' => 'features',
                    'background' => 'tint',
                    'anchor' => 'our-values',
                    'eyebrow' => 'Our Values',
                    'heading' => "What drives\nevery project.",
                    'description' => 'Four principles shape how we plan, build and support every website and application we deliver.',
                    'link_label' => 'Start a Project',
                    'link_url' => '/contact',
                    'gallery_items' => [
                        ['media_id' => null, 'url' => null, 'icon' => 'lightbulb', 'title' => 'Innovation First', 'description' => 'We embrace cutting-edge technologies to build future-proof platforms.'],
                        ['media_id' => null, 'url' => null, 'icon' => 'code', 'title' => 'Precision Craftsmanship', 'description' => 'We write clean, optimized code that performs beautifully.'],
                        ['media_id' => null, 'url' => null, 'icon' => 'handshake', 'title' => 'Client First', 'description' => 'We partner with you every step of the way — your success is our mission.'],
                        ['media_id' => null, 'url' => null, 'icon' => 'chart', 'title' => 'Results Driven', 'description' => "Our websites don't just look good — they work hard to drive conversions."],
                    ],
                ],
            ],
            // AI Development / Cloud & DevOps / Digital Marketing spotlight.
            require database_path('seeders/data/specialised-expertise-section.php'),
            [
                'section_type' => PageSectionType::Gallery->value,
                'title' => 'Technical Expertise',
                'is_enabled' => true,
                'content_json' => [
                    'display' => 'tech_groups',
                    'background' => 'white',
                    'anchor' => 'expertise',
                    'eyebrow' => 'Our Technical Expertise',
                    'heading' => 'The right tools for every kind of project.',
                    'link_label' => 'Explore Our Services',
                    'link_url' => '/services',
                    'gallery_items' => array_map(fn (array $item): array => ['media_id' => null, 'url' => null, 'description' => null, ...$item], [
                        ['group' => 'CMS & E-commerce', 'title' => 'Laravel', 'icon' => 'laravel'],
                        ['group' => 'CMS & E-commerce', 'title' => 'WordPress', 'icon' => 'wordpress'],
                        ['group' => 'CMS & E-commerce', 'title' => 'WooCommerce', 'icon' => 'woocommerce'],
                        ['group' => 'CMS & E-commerce', 'title' => 'Shopify', 'icon' => 'shopify'],
                        ['group' => 'CMS & E-commerce', 'title' => 'Magento', 'icon' => 'magento'],
                        ['group' => 'Python', 'title' => 'Python', 'icon' => 'python'],
                        ['group' => 'Python', 'title' => 'Django', 'icon' => 'django'],
                        ['group' => 'Python', 'title' => 'Flask', 'icon' => 'flask'],
                        ['group' => 'Python', 'title' => 'FastAPI', 'icon' => 'fastapi'],
                        ['group' => 'JavaScript', 'title' => 'React', 'icon' => 'react'],
                        ['group' => 'JavaScript', 'title' => 'Next.js', 'icon' => 'nextjs'],
                        ['group' => 'JavaScript', 'title' => 'Angular', 'icon' => 'angular'],
                        ['group' => 'JavaScript', 'title' => 'Express', 'icon' => 'express'],
                        ['group' => 'JavaScript', 'title' => 'Node.js', 'icon' => 'nodejs'],
                        ['group' => 'APIs, Data & DevOps', 'title' => 'REST APIs', 'icon' => 'nodes'],
                        ['group' => 'APIs, Data & DevOps', 'title' => 'GraphQL', 'icon' => 'graphql'],
                        ['group' => 'APIs, Data & DevOps', 'title' => 'MySQL', 'icon' => 'mysql'],
                        ['group' => 'APIs, Data & DevOps', 'title' => 'PostgreSQL', 'icon' => 'postgresql'],
                        ['group' => 'APIs, Data & DevOps', 'title' => 'MongoDB', 'icon' => 'mongodb'],
                        ['group' => 'APIs, Data & DevOps', 'title' => 'Git', 'icon' => 'git'],
                        ['group' => 'APIs, Data & DevOps', 'title' => 'Docker', 'icon' => 'docker'],
                        ['group' => 'APIs, Data & DevOps', 'title' => 'Webpack', 'icon' => 'webpack'],
                    ]),
                ],
            ],
            [
                'section_type' => PageSectionType::ImageText->value,
                'title' => 'Meet the Founder',
                'is_enabled' => true,
                'content_json' => [
                    'style' => 'profile',
                    'background' => 'tint',
                    'anchor' => 'founder',
                    'eyebrow' => 'Meet the Founder',
                    'heading' => 'Susanta Bera',
                    'subheading' => 'Founder & Full-Stack Developer',
                    'text' => "With over 20 years in the web development space, I've built, scaled, and optimized digital products for clients across industries.\n\nSoftphoria is the next step in my journey — bringing that expertise directly to businesses that want to win online.",
                    'cta_label' => "Let's Talk",
                    'cta_url' => '/contact',
                    'media_id' => $founderPhoto?->id,
                ],
            ],
            [
                'section_type' => PageSectionType::Cta->value,
                'title' => 'Closing Call to Action',
                'is_enabled' => true,
                'content_json' => [
                    'style' => 'banner',
                    'eyebrow' => 'Ready when you are',
                    'heading' => "Let's build something great together.",
                    'description' => 'Have a project idea or need help improving your online presence?',
                    'cta_label' => 'Get a Free Consultation',
                    'cta_url' => '/contact',
                    'secondary_cta_label' => 'View Our Services',
                    'secondary_cta_url' => '/services',
                ],
            ],
        ];
    }

    private function isPlaceholder(Page $page): bool
    {
        $bodies = $page->sections->map(fn ($section): string => strip_tags((string) json_encode($section->content_json)))->implode(' ');

        return $page->sections->isEmpty() || str_contains($bodies, 'Long Description');
    }

    /**
     * Copies a bundled image into the Media Library once; later runs reuse
     * the existing row (matched by original filename).
     */
    private function importImage(string $filename, string $altText, User $actor): ?Media
    {
        $existing = Media::query()->where('original_filename', $filename)->first();

        if ($existing) {
            return $existing;
        }

        $source = database_path("seeders/assets/about/{$filename}");

        if (! is_file($source)) {
            return null;
        }

        $disk = MediaCategory::Image->diskName();
        $path = trim(config('media.categories.image.directory', 'media/images'), '/').'/about-'.$filename;
        Storage::disk($disk)->put($path, file_get_contents($source));

        $media = app(StoreUploadedMediaAction::class)->handle($disk, $path, $actor);
        $media->forceFill(['original_filename' => $filename, 'alt_text' => $altText])->save();

        return $media;
    }
}
