<?php

namespace Database\Seeders;

use App\Actions\Page\CreatePageAction;
use App\Actions\Page\UpdatePageAction;
use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Models\Page;
use App\Models\PortfolioItem;
use App\Models\Testimonial;
use App\Models\User;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seeds the "home" CMS Page with Softphoria's homepage content through the
 * same CreatePageAction/UpdatePageAction the Pages admin UI uses, so it
 * behaves exactly like an admin-authored page — sections, revision
 * snapshot, SEO — rather than a second, parallel content path.
 * Idempotent: re-running it updates the existing "home" page instead of
 * duplicating it.
 *
 * WEB-103: copy and section order follow the approved redesign
 * (docs/Reference UI/develop/home.png). Every media reference (hero image,
 * testimonial/CTA backgrounds, item images) is carried forward from what's
 * already saved rather than hardcoded, since Media IDs differ per
 * environment — pick them once in Admin → Pages → Home.
 *
 * Client testimonials now live in the admin Testimonials resource (the
 * Testimonials section reads them live). The five below are the real,
 * verbatim quotes from https://softphoria.com/ that WEB-102 transcribed;
 * no testimonial is invented here — the redesign mockup's sample quote is
 * not seeded.
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

        $existingPage = Page::query()->where('slug', 'home')->with('sections')->first();
        $existingSections = $existingPage?->sections ?? new Collection;

        $data = [
            'title' => 'Home',
            'slug' => 'home',
            'template' => PageTemplate::Custom->value,
            'status' => PageStatus::Published->value,
            'summary' => 'We design, build and support high-performance websites, custom software, cloud infrastructure and integrations — helping businesses turn ideas into real world solutions.',
            'publish_at' => now(),
            'sections' => [
                [
                    'section_type' => PageSectionType::Hero->value,
                    'title' => 'Homepage Hero',
                    'is_enabled' => true,
                    'content_json' => [
                        'eyebrow' => 'Web · Software · Cloud · Integration',
                        'heading' => 'Technology that moves your',
                        'heading_highlight' => 'business forward.',
                        'subheading' => 'We design, build and support high-performance websites, custom software, cloud infrastructure and integrations — helping businesses turn ideas into real world solutions.',
                        'cta_label' => 'Start a Project',
                        'cta_url' => '/contact',
                        'secondary_cta_label' => 'Our Services',
                        'secondary_cta_url' => '/#services',
                        'stats' => [
                            ['value' => '20+', 'label' => 'Years Experience'],
                            ['value' => '100+', 'label' => 'Projects Delivered'],
                            ['value' => 'Long-term', 'label' => 'Client Relationships'],
                        ],
                        'media_id' => $this->existingValue($existingSections, PageSectionType::Hero, null, 'media_id'),
                    ],
                ],
                $this->gallery($existingSections, 'Trusted Technologies', 'logos', [
                    'eyebrow' => 'Trusted Technologies',
                    'background' => 'white',
                ], [
                    ['title' => 'Laravel', 'icon' => 'laravel'],
                    ['title' => 'Python', 'icon' => 'python'],
                    ['title' => 'Django', 'icon' => 'django'],
                    ['title' => 'Node.js', 'icon' => 'nodejs'],
                    ['title' => 'Next.js', 'icon' => 'nextjs'],
                    ['title' => 'AWS', 'icon' => 'aws'],
                    ['title' => 'Docker', 'icon' => 'docker'],
                    ['title' => 'NGINX', 'icon' => 'nginx'],
                    ['title' => 'MySQL', 'icon' => 'mysql'],
                    ['title' => 'PostgreSQL', 'icon' => 'postgresql'],
                    ['title' => 'Shopify', 'icon' => 'shopify'],
                    ['title' => 'Claude Code', 'icon' => 'claude'],
                    ['title' => 'Replit', 'icon' => 'replit'],
                    ['title' => 'Lovable', 'icon' => 'lovable'],
                ]),
                $this->gallery($existingSections, 'Services', 'services', [
                    'anchor' => 'services',
                    'eyebrow' => 'Our Services',
                    'heading' => 'Technology solutions built around your business.',
                    'description' => 'From idea to implementation, we deliver reliable, scalable and maintainable solutions.',
                    'link_label' => 'View All Services',
                    'link_url' => '#',
                    'item_link_label' => 'Learn More',
                    'background' => 'tint',
                ], [
                    ['title' => 'Web Development', 'description' => 'Modern, responsive and high-performance websites tailored to your business goals.', 'icon' => 'monitor', 'url' => '#'],
                    ['title' => 'Custom Software', 'description' => 'Business applications designed around your specific workflow and requirements.', 'icon' => 'code', 'url' => '#'],
                    ['title' => 'E-Commerce Solutions', 'description' => 'Scalable e-commerce platforms with integrations and automation.', 'icon' => 'cart', 'url' => '#'],
                    ['title' => 'Cloud & DevOps', 'description' => 'AWS infrastructure, migration, monitoring, security and performance optimization.', 'icon' => 'cloud', 'url' => '#'],
                    ['title' => 'API & System Integrations', 'description' => 'Connect your website, applications, ERP, CRM and third-party services.', 'icon' => 'nodes', 'url' => '#'],
                    ['title' => 'CMS & Content Platforms', 'description' => 'Flexible and easy-to-manage content solutions for your team.', 'icon' => 'document', 'url' => '#'],
                ]),
                $this->gallery($existingSections, 'Why Softphoria', 'features', [
                    'anchor' => 'why-softphoria',
                    'eyebrow' => 'Why Softphoria?',
                    'heading' => "More than a website.\nA technology partner.",
                    'description' => 'We combine technical expertise with practical business understanding to deliver solutions that make a real difference. From planning to launch and beyond, we\'re with you at every step.',
                    'link_label' => 'About Softphoria',
                    'link_url' => '/about',
                    'background' => 'white',
                ], [
                    ['title' => 'Understand', 'description' => 'We learn about your business, users and objectives.', 'icon' => 'users'],
                    ['title' => 'Build', 'description' => 'We create solutions using proven technologies.', 'icon' => 'cog'],
                    ['title' => 'Integrate', 'description' => 'We connect your digital systems so they work together.', 'icon' => 'link'],
                    ['title' => 'Support', 'description' => 'We stay involved beyond launch to maintain and improve your platform.', 'icon' => 'headset'],
                ]),
                // Cards come from Admin → Portfolio (featured rows) — see seedPortfolioItems().
                [
                    'section_type' => PageSectionType::Portfolio->value,
                    'title' => 'Featured Portfolio',
                    'is_enabled' => true,
                    'content_json' => [
                        'anchor' => 'portfolio',
                        'eyebrow' => 'Featured Portfolio',
                        'heading' => 'Selected projects',
                        'link_label' => 'View All Projects',
                        'link_url' => '#',
                        'item_link_label' => 'View Case Study',
                        'background' => 'tint',
                        'limit' => 6,
                    ],
                ],
                $this->gallery($existingSections, 'Technologies', 'tech_groups', [
                    'anchor' => 'technologies',
                    'eyebrow' => 'Technologies We Work With',
                    'heading' => 'Modern tools for modern solutions.',
                    'link_label' => 'View All Technologies',
                    'link_url' => '#',
                    'background' => 'white',
                ], [
                    ['group' => 'Backend', 'title' => 'Python', 'icon' => 'python'],
                    ['group' => 'Backend', 'title' => 'Django', 'icon' => 'django'],
                    ['group' => 'Backend', 'title' => 'Flask', 'icon' => 'flask'],
                    ['group' => 'Backend', 'title' => 'Laravel', 'icon' => 'laravel'],
                    ['group' => 'Backend', 'title' => 'PHP', 'icon' => 'php'],
                    ['group' => 'Frontend', 'title' => 'HTML', 'icon' => 'html5'],
                    ['group' => 'Frontend', 'title' => 'CSS3', 'icon' => 'css3'],
                    ['group' => 'Frontend', 'title' => 'JavaScript', 'icon' => 'javascript'],
                    ['group' => 'Frontend', 'title' => 'Tailwind', 'icon' => 'tailwind'],
                    ['group' => 'Cloud & DevOps', 'title' => 'AWS', 'icon' => 'aws'],
                    ['group' => 'Cloud & DevOps', 'title' => 'Docker', 'icon' => 'docker'],
                    ['group' => 'Cloud & DevOps', 'title' => 'Kubernetes', 'icon' => 'kubernetes'],
                    ['group' => 'Cloud & DevOps', 'title' => 'Nginx', 'icon' => 'nginx'],
                    ['group' => 'Databases', 'title' => 'MySQL', 'icon' => 'mysql'],
                    ['group' => 'Databases', 'title' => 'PostgreSQL', 'icon' => 'postgresql'],
                    ['group' => 'Databases', 'title' => 'Redis', 'icon' => 'redis'],
                ]),
                $this->gallery($existingSections, 'Our Process', 'steps', [
                    'anchor' => 'process',
                    'eyebrow' => 'Our Process',
                    'heading' => 'From idea to launch.',
                    'background' => 'tint',
                ], [
                    ['title' => 'Discover', 'description' => 'Understand your business, users and requirements.', 'icon' => 'search'],
                    ['title' => 'Plan', 'description' => 'Architecture, technology and implementation plan.', 'icon' => 'plan'],
                    ['title' => 'Build', 'description' => 'Design, development, integration and testing.', 'icon' => 'code'],
                    ['title' => 'Deliver', 'description' => 'Launch, optimization, and ongoing support.', 'icon' => 'rocket'],
                ]),
                [
                    'section_type' => PageSectionType::Testimonials->value,
                    'title' => 'Testimonials',
                    'is_enabled' => true,
                    'content_json' => [
                        'anchor' => 'testimonials',
                        'eyebrow' => 'What Our Clients Say',
                        'heading' => 'Trusted by businesses worldwide.',
                        'autoplay_seconds' => 6,
                        'background_media_id' => $this->existingValue($existingSections, PageSectionType::Testimonials, null, 'background_media_id'),
                    ],
                ],
                // Cards come from Blog → Posts (newest live posts); the
                // section hides itself until something is published.
                [
                    'section_type' => PageSectionType::BlogPosts->value,
                    'title' => 'Latest Insights',
                    'is_enabled' => true,
                    'content_json' => [
                        'anchor' => 'insights',
                        'eyebrow' => 'Latest Insights',
                        'heading' => 'Ideas, tutorials and technology.',
                        'link_label' => 'View All Articles',
                        'link_url' => '/blog',
                        'background' => 'white',
                        'limit' => 3,
                    ],
                ],
                [
                    'section_type' => PageSectionType::Cta->value,
                    'title' => 'Closing Call to Action',
                    'is_enabled' => true,
                    'content_json' => [
                        'style' => 'banner',
                        'eyebrow' => 'Have a project in mind?',
                        'heading' => 'Let\'s build something great together.',
                        'description' => 'Tell us about your business, your challenge and how we can help.',
                        'cta_label' => 'Start a Conversation',
                        'cta_url' => '/contact',
                        'secondary_cta_label' => 'View Our Services',
                        'secondary_cta_url' => '/#services',
                        'background_media_id' => $this->existingValue($existingSections, PageSectionType::Cta, null, 'background_media_id'),
                    ],
                ],
            ],
            'seo' => [
                'meta_title' => 'Softphoria — Technology that moves your business forward',
                'meta_description' => 'We design, build and support high-performance websites, custom software, cloud infrastructure and integrations — helping businesses turn ideas into real world solutions.',
            ],
        ];

        if ($existingPage) {
            app(UpdatePageAction::class)->handle($existingPage, $data, $actor);
        } else {
            app(CreatePageAction::class)->handle($data, $actor);
        }

        $this->seedTestimonials();
        $this->seedPortfolioItems();
        $this->seedContactSettingsIfUnset();
    }

    /**
     * One Gallery section in the given display, carrying forward any item
     * images an admin has since picked (matched by item title).
     *
     * @param  array<string, mixed>  $content
     * @param  array<int, array<string, string>>  $items
     * @return array<string, mixed>
     */
    private function gallery(Collection $existingSections, string $title, string $display, array $content, array $items): array
    {
        $existingItems = collect($existingSections
            ->first(fn ($section) => $section->section_type === PageSectionType::Gallery->value
                && ($section->content_json['display'] ?? null) === $display)
            ?->content_json['gallery_items'] ?? [])
            ->keyBy('title');

        return [
            'section_type' => PageSectionType::Gallery->value,
            'title' => $title,
            'is_enabled' => true,
            'content_json' => [
                'display' => $display,
                ...$content,
                'gallery_items' => array_map(fn (array $item): array => [
                    'media_id' => $existingItems->get($item['title'])['media_id'] ?? null,
                    'url' => null,
                    ...$item,
                ], $items),
            ],
        ];
    }

    /**
     * WEB-102 browser-verification pass: re-running this seeder used to
     * silently wipe out media an admin had since picked through the Pages
     * editor — carrying forward whatever is already saved avoids that.
     */
    private function existingValue(Collection $existingSections, PageSectionType $type, ?string $display, string $key): mixed
    {
        return $existingSections
            ->first(fn ($section) => $section->section_type === $type->value
                && ($display === null || ($section->content_json['display'] ?? null) === $display))
            ?->content_json[$key] ?? null;
    }

    /**
     * The real client quotes from https://softphoria.com/ (verbatim — these
     * are third-party statements, not our own copy). Matched by name, so a
     * re-seed never duplicates them or overwrites an admin's later edits.
     */
    /**
     * The redesign's three sample projects, as featured portfolio items.
     * Only seeded into an empty table, so re-running never overwrites or
     * re-adds projects an admin has edited or removed.
     */
    private function seedPortfolioItems(): void
    {
        if (PortfolioItem::query()->exists()) {
            return;
        }

        $items = [
            ['title' => 'B2B E-Commerce Platform', 'category' => 'E-Commerce', 'summary' => 'A large scale e-commerce platform with complex product management, pricing and ERP integration.', 'icon' => 'cart'],
            ['title' => '3D Configurator Application', 'category' => 'Custom Software', 'summary' => 'Interactive 3D product configurator with backend integration and cloud deployment.', 'icon' => 'cog'],
            ['title' => 'Cloud Migration & Modernization', 'category' => 'Cloud & DevOps', 'summary' => 'Migration and modernization of existing applications to AWS with improved scalability and security.', 'icon' => 'aws'],
        ];

        foreach ($items as $index => $item) {
            PortfolioItem::query()->create([...$item, 'sort_order' => $index, 'is_featured' => true, 'is_published' => true]);
        }
    }

    private function seedTestimonials(): void
    {
        $testimonials = [
            ['name' => 'John B.', 'designation' => 'Experienced Linux Administrator of @Brsox', 'message' => 'This guy is amazing! Did exactly as I wanted and impressed me every bit of the way. If you need some work done, this guy is the man for the job! A++'],
            ['name' => 'Mark F.', 'designation' => 'CEO at Salus Technology Services Ltd', 'message' => 'Susanta is a talented and dedicated professional. He successfully delivered our complex Student Management Application and consistently brought passion and enthusiasm to every project. Highly recommended!'],
            ['name' => 'Saikiran', 'designation' => null, 'message' => 'Susanta, the most impressive programmer. We can just leave him works and can relax. He could do tasks very well than we expect from him. Even he faced many obstacles from my coding, he can over ride them and successfully completed my tasks very well.'],
            ['name' => 'Michael C. Gill', 'designation' => 'Founder & CEO of 2K Computer Solutions', 'message' => 'Excellent programmer, very good work with fast communication, highly recommended.'],
            ['name' => 'Dr. Tano', 'designation' => 'Founder of Integrative Immunity Health System', 'message' => 'Susanta has built strong, long-term relationships across multiple technologies and programming languages. He manages them efficiently and consistently delivers with excellence.'],
        ];

        foreach ($testimonials as $index => $testimonial) {
            Testimonial::query()->firstOrCreate(
                ['name' => $testimonial['name']],
                [...$testimonial, 'sort_order' => $index, 'is_enabled' => true],
            );
        }
    }

    /**
     * The real Softphoria contact details (also from https://softphoria.com/),
     * shown on the Contact page (WEB-101 items C/D/F). Only fills in a value
     * that's genuinely unset, so re-seeding never overwrites anything an
     * admin has since edited through Website Setup — see also
     * resetLegacyJacobBrandingIfPresent() below.
     */
    private function seedContactSettingsIfUnset(): void
    {
        $settings = app(SettingsRepository::class);

        $this->resetLegacyJacobBrandingIfPresent($settings);

        if (blank($settings->get('contact', 'email'))) {
            $settings->set('contact', 'email', 'contact@softphoria.com');
        }

        if (blank($settings->get('contact', 'phone'))) {
            $settings->set('contact', 'phone', '+91 9163270494');
        }

        if (blank($settings->get('contact', 'address'))) {
            $settings->set('contact', 'address', 'Monalisa Mansion, Nayabad Ave, Kolkata, India');
        }
    }

    /**
     * WEB-102: site_name/logo/footer-background were saved as literal
     * "All The Things Light" values and its uploaded imagery — the exact
     * "homepage-specific Jacob fallbacks" that ticket required removing.
     * WEB-103: the logo/footer images are now only cleared while the
     * legacy site name is still in place, i.e. once on a leftover Jacob
     * install — previously every re-seed wiped out whatever logo a
     * Softphoria admin had since uploaded.
     */
    private function resetLegacyJacobBrandingIfPresent(SettingsRepository $settings): void
    {
        $siteName = $settings->get('general', 'site_name');

        if ($siteName === 'All The Things Light') {
            $settings->set('general', 'logo_media_id', null, 'integer');
            $settings->set('footer', 'logo_media_id', null, 'integer');
            $settings->set('footer', 'background_media_id', null, 'integer');
        }

        if (in_array($siteName, [null, '', 'All The Things Light'], true)) {
            $settings->set('general', 'site_name', 'Softphoria');
        }

        // WEB-102 browser-verification pass: "Be your tech partner" is the
        // real tagline shown under the logo on the live site — verified via
        // browser, not fabricated. Never overwrites a tagline an admin has
        // since set.
        if (blank($settings->get('general', 'tagline'))) {
            $settings->set('general', 'tagline', 'Be your tech partner');
        }

        if (in_array($settings->get('footer', 'subheading'), [
            null,
            'A creative home for music, writing, reflection, thinking, and community.',
            // WEB-102's own earlier seeded value, superseded by the redesign.
            'We specialize in delivering custom software, enterprise solutions, and digital transformation services across industries.',
        ], true)) {
            $settings->set('footer', 'subheading', 'Technology solutions for ambitious businesses.');
        }
    }
}
