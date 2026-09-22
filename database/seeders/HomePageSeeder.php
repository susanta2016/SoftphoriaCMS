<?php

namespace Database\Seeders;

use App\Actions\Page\CreatePageAction;
use App\Actions\Page\UpdatePageAction;
use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Models\Page;
use App\Models\User;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Database\Seeder;

/**
 * Seeds the "home" CMS Page with Softphoria's real homepage content
 * (WEB-102), through the same CreatePageAction/UpdatePageAction the Pages
 * admin UI uses, so it behaves exactly like an admin-authored page —
 * sections, revision snapshot, SEO — rather than a second, parallel content
 * path. Idempotent: re-running it updates the existing "home" page instead
 * of duplicating it.
 *
 * Content below is transcribed from the live https://softphoria.com/ (and,
 * for the Expertise list only, https://softphoria.com/about-us — the live
 * homepage itself has no dedicated Expertise section) as the authoritative
 * source, per WEB-102. Two verbatim wording issues were left uncorrected
 * deliberately — see the Process/Testimonials sections below — and two
 * trivial fixes were made: the hero headline's capitalization/grammar
 * ("Technology & it solution" → "Technology & IT Solutions") and a
 * duplicated-word typo in the "Who We Are" paragraph ("We are the
 * specializes in..." → "We specialize in..."). Nothing else was reworded,
 * and no services/testimonials/stats/technologies were invented.
 *
 * Replaces WEB-001..005's previous "All The Things Light" placeholder
 * content entirely — see PR/commit history for that prior version.
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

        $data = [
            'title' => 'Home',
            'slug' => 'home',
            'template' => PageTemplate::Custom->value,
            'status' => PageStatus::Published->value,
            'summary' => 'We specialize in delivering custom software, enterprise solutions, and digital transformation services across industries.',
            'publish_at' => now(),
            'sections' => [
                [
                    'section_type' => PageSectionType::Hero->value,
                    'title' => 'Homepage Hero',
                    'is_enabled' => true,
                    'content_json' => [
                        'heading' => 'Technology & IT Solutions',
                        'subheading' => 'Excellent IT Services for your success',
                        'cta_label' => 'Read More',
                        'cta_url' => '#',
                    ],
                ],
                [
                    'section_type' => PageSectionType::RichText->value,
                    'title' => null,
                    'is_enabled' => true,
                    'content_json' => [
                        'body' => '<h2>Who We Are</h2>'
                            .'<p><strong>Inspiring Spaces for Innovative Minds</strong></p>'
                            .'<p>We specialize in delivering custom software, enterprise solutions, and digital transformation services across industries.</p>',
                    ],
                ],
                [
                    'section_type' => PageSectionType::Gallery->value,
                    'title' => 'Services',
                    'is_enabled' => true,
                    'content_json' => [
                        'display' => 'grid',
                        'gallery_items' => [
                            [
                                'title' => 'Creative Design',
                                'description' => 'Build a distinctive brand identity that captures attention, engages your audience, and leaves a memorable impression.',
                                'url' => '#',
                            ],
                            [
                                'title' => 'Web Development',
                                'description' => 'Bring your vision to life with a beautifully crafted, user-centric website—designed for seamless performance and modern appeal.',
                                'url' => '#',
                            ],
                            [
                                'title' => 'Mobile Application',
                                'description' => 'Develop powerful, intuitive apps customized to your needs. Deliver exceptional user experiences and grow your digital impact.',
                                'url' => '#',
                            ],
                        ],
                    ],
                ],
                [
                    'section_type' => PageSectionType::RichText->value,
                    'title' => null,
                    'is_enabled' => true,
                    'content_json' => [
                        // From https://softphoria.com/about-us's "Our Technical
                        // Expertise" list — the live homepage has no Expertise
                        // section of its own.
                        'body' => '<h2>Expertise</h2><ul>'
                            .'<li>Laravel</li><li>WordPress</li><li>WooCommerce</li>'
                            .'<li>Shopify</li><li>Magento</li>'
                            .'<li>Python (Django, Flask, FastAPI)</li>'
                            .'<li>React.js</li><li>Next.js</li><li>Angular.js</li>'
                            .'<li>Express.js</li><li>Node.js</li>'
                            .'<li>REST APIs</li><li>GraphQL</li>'
                            .'<li>MySQL</li><li>PostgreSQL</li><li>MongoDB</li>'
                            .'<li>Git</li><li>Docker</li><li>Webpack</li>'
                            .'</ul>',
                    ],
                ],
                [
                    'section_type' => PageSectionType::Gallery->value,
                    'title' => 'Helping Your Business Grow and Succeed',
                    'is_enabled' => true,
                    'content_json' => [
                        'display' => 'steps',
                        // Verbatim from the live site — the Planning/Execute
                        // step descriptions read like they may be swapped
                        // relative to their titles, but this was consistent
                        // across independent reads of the live page, so it
                        // was preserved rather than "corrected" (WEB-102:
                        // preserve actual content, don't rewrite claims).
                        'gallery_items' => [
                            ['title' => 'Discovery', 'description' => 'We dive deep to understand your goals, audience, and challenges.'],
                            ['title' => 'Planning', 'description' => 'We bring ideas to life with precision, creativity, and agility.'],
                            ['title' => 'Execute', 'description' => 'We craft a clear, strategic roadmap tailored to your vision.'],
                            ['title' => 'Deliver', 'description' => 'We launch with impact, ensuring quality, performance, and satisfaction.'],
                        ],
                    ],
                ],
                [
                    'section_type' => PageSectionType::Gallery->value,
                    'title' => 'What Clients Say',
                    'is_enabled' => true,
                    'content_json' => [
                        'display' => 'quotes',
                        // Verbatim client quotes — not reworded, since these
                        // are third-party statements, not our own copy.
                        'gallery_items' => [
                            [
                                'title' => 'John B. — Experienced Linux Administrator of @Brsox',
                                'description' => 'This guy is amazing! Did exactly as I wanted and impressed me every bit of the way. If you need some work done, this guy is the man for the job! A++',
                            ],
                            [
                                'title' => 'Mark F. — CEO at Salus Technology Services Ltd',
                                'description' => 'Susanta is a talented and dedicated professional. He successfully delivered our complex Student Management Application and consistently brought passion and enthusiasm to every project. Highly recommended!',
                            ],
                            [
                                'title' => 'Saikiran',
                                'description' => 'Susanta, the most impressive programmer. We can just leave him works and can relax. He could do tasks very well than we expect from him. Even he faced many obstacles from my coding, he can over ride them and successfully completed my tasks very well.',
                            ],
                            [
                                'title' => 'Michael C. Gill — Founder & CEO of 2K Computer Solutions',
                                'description' => 'Excellent programmer, very good work with fast communication, highly recommended.',
                            ],
                            [
                                'title' => 'Dr. Tano — Founder of Integrative Immunity Health System',
                                'description' => 'Susanta has built strong, long-term relationships across multiple technologies and programming languages. He manages them efficiently and consistently delivers with excellence.',
                            ],
                        ],
                    ],
                ],
                [
                    'section_type' => PageSectionType::ContactForm->value,
                    'title' => 'Free Consultation',
                    'is_enabled' => true,
                    'content_json' => [],
                ],
            ],
            'seo' => [
                'meta_title' => 'Softphoria — Technology & IT Solutions',
                'meta_description' => 'We specialize in delivering custom software, enterprise solutions, and digital transformation services across industries.',
            ],
        ];

        $page = Page::query()->where('slug', 'home')->first();

        if ($page) {
            app(UpdatePageAction::class)->handle($page, $data, $actor);
        } else {
            app(CreatePageAction::class)->handle($data, $actor);
        }

        $this->seedContactSettingsIfUnset();
    }

    /**
     * The real Softphoria contact details (also from https://softphoria.com/),
     * shown on both the dedicated Contact page and the homepage's Free
     * Consultation section (WEB-101 items C/D/F). Only fills in a value
     * that's genuinely unset, so re-seeding never overwrites anything an
     * admin has since edited through Website Setup — except the four
     * General/Footer values handled in resetLegacyJacobBrandingIfPresent()
     * below, which this environment had literally saved as "All The Things
     * Light" / its uploaded logo+footer-background images (leftover from
     * this Page's previous content, not something a Softphoria admin
     * chose), and which WEB-102 explicitly requires removing wherever
     * found — see that method's own docblock.
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
     * "All The Things Light" values and its uploaded imagery (not merely
     * empty — they'd have passed a blank() guard) — the exact "homepage-
     * specific Jacob fallbacks" this ticket requires removing, since they
     * render directly on the homepage via the shared header/footer chrome.
     * Cleared to null rather than replaced with a fabricated Softphoria
     * logo/background (no real asset available to upload here) — the
     * header/footer already fall back to a plain text brand-mark
     * (x-site.brand-mark) once no logo is set, and site_name/footer
     * subheading get real Softphoria text below. Only touches these exact
     * known-stale values, never anything an admin has genuinely set since.
     */
    private function resetLegacyJacobBrandingIfPresent(SettingsRepository $settings): void
    {
        if (in_array($settings->get('general', 'site_name'), [null, '', 'All The Things Light'], true)) {
            $settings->set('general', 'site_name', 'Softphoria');
        }

        if ($settings->get('general', 'logo_media_id')) {
            $settings->set('general', 'logo_media_id', null, 'integer');
        }

        if ($settings->get('footer', 'logo_media_id')) {
            $settings->set('footer', 'logo_media_id', null, 'integer');
        }

        if ($settings->get('footer', 'background_media_id')) {
            $settings->set('footer', 'background_media_id', null, 'integer');
        }

        if (in_array($settings->get('footer', 'subheading'), [
            null,
            'A creative home for music, writing, reflection, thinking, and community.',
        ], true)) {
            $settings->set('footer', 'subheading', 'We specialize in delivering custom software, enterprise solutions, and digital transformation services across industries.');
        }
    }
}
