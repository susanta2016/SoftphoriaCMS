<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SEO-001 — content only (no schema change): search titles/descriptions for
 * the public pages, derived from their existing published copy.
 *
 * - Only EMPTY fields are filled. A title/description an admin has already
 *   written (e.g. About's, Home's, the legal pages', the tools') is never
 *   overwritten, and a record whose slug no longer exists is skipped.
 * - Open Graph / Twitter fields are left empty on purpose: SeoTagBuilder
 *   falls back to the meta title/description, so they can't drift apart.
 *   About gets its own "Our Story" illustration as its share image.
 * - CMS Page canonical URLs that just repeat the page's own path (saved as
 *   an absolute URL, e.g. http://localhost:8080/about) are reset to NULL =
 *   automatic, so they follow APP_URL again (see SavesPageSeo).
 * - Sanjog Loan is left alone: it has no summary to describe it from.
 *
 * Plain query-builder code on purpose, so later model changes can't break it.
 */
return new class extends Migration
{
    private const SERVICE = 'App\\Models\\Service';

    private const PORTFOLIO_ITEM = 'App\\Models\\PortfolioItem';

    private const PAGE = 'App\\Models\\Page';

    /** settings group => [meta_title, meta_description] */
    private const LANDING_PAGES = [
        'services' => [
            'Web, Software, Cloud & AI Development Services | Softphoria',
            'Websites, custom software, e-commerce, cloud & DevOps, API integrations, CMS platforms, AI and digital marketing, planned, built and supported by Softphoria.',
        ],
        'portfolio' => [
            'Portfolio: Selected Projects | Softphoria',
            'Selected Softphoria projects, including e-commerce platforms, cloud migrations and business applications, with the technology behind each one.',
        ],
        'blog' => [
            'Insights & Articles | Softphoria',
            'Practical technology guides, engineering deep-dives and lessons from real client projects, written by Softphoria to help you make better technology decisions.',
        ],
        'contact' => [
            'Contact Softphoria | Discuss Your Project',
            'Have a project in mind, a question about our services or need support? Message, email, call or WhatsApp Softphoria, and a real person will reply.',
        ],
        'tools' => [
            'Free Web & Marketing Tools | Softphoria',
            'Free tools from the Softphoria team, such as a PX to REM converter and a UTM campaign URL builder. Practical calculators and converters, no sign-up needed.',
        ],
    ];

    /** service slug => [meta_title, meta_description] */
    private const SERVICES = [
        'web-development' => [
            'Web Development Services | Softphoria',
            'Responsive, fast and search-friendly websites built around your goals, with an easy admin panel for your team plus security and care after launch.',
        ],
        'custom-software' => [
            'Custom Software Development | Softphoria',
            'Business software shaped around your workflow: portals, dashboards, internal tools and automation, delivered in phases with ongoing development after launch.',
        ],
        'e-commerce-solutions' => [
            'E-Commerce Development: Shopify & Custom Stores | Softphoria',
            'B2B and B2C online stores on Shopify or a custom platform, with secure payments, shipping and ERP, inventory and accounting integrations kept in sync.',
        ],
        'cloud-devops' => [
            'Cloud & DevOps Services on AWS | Softphoria',
            'AWS cloud architecture, migration with minimal downtime, CI/CD pipelines, monitoring, security hardening and cost optimization for your applications.',
        ],
        'api-system-integrations' => [
            'API Development & System Integrations | Softphoria',
            'Connect your website, apps, ERP, CRM and third-party services. Secure, documented APIs and integrations built with queues, retries and alerts.',
        ],
        'cms-content-platforms' => [
            'CMS & Content Platform Development | Softphoria',
            'Content platforms with custom admin panels, media libraries, editorial workflows and built-in SEO, so your team can publish without waiting on developers.',
        ],
        'ai-development' => [
            'AI Development: Assistants, Automation & LLMs | Softphoria',
            'Practical AI for your business: assistants that answer questions from your content, workflow automation and LLM integrations, built with privacy in mind.',
        ],
        'digital-marketing' => [
            'Digital Marketing & SEO Services | Softphoria',
            'Technical SEO, content marketing, search and social ad campaigns and clear analytics reporting, so the right customers find your business and get in touch.',
        ],
    ];

    /** portfolio slug => [meta_title, meta_description] */
    private const PORTFOLIO_ITEMS = [
        'b2b-e-commerce-platform' => [
            'B2B E-Commerce Platform Project | Softphoria',
            'A large-scale B2B e-commerce platform delivered by Softphoria, with complex product management, pricing and ERP integration.',
        ],
        'cloud-migration-modernization' => [
            'Cloud Migration & Modernization on AWS | Softphoria',
            'How Softphoria migrated and modernized existing applications on AWS for improved scalability and security: a cloud migration and modernization project.',
        ],
    ];

    /** The About page's "Our Story" illustration, imported by AboutPageSeeder. */
    private const ABOUT_SHARE_IMAGE_PATH = 'media/images/about-softphoria-story.jpg';

    public function up(): void
    {
        foreach (self::LANDING_PAGES as $group => [$title, $description]) {
            $this->fillSetting($group, 'meta_title', $title);
            $this->fillSetting($group, 'meta_description', $description);
        }

        foreach (self::SERVICES as $slug => $meta) {
            $this->fillSeo(self::SERVICE, DB::table('services')->where('slug', $slug)->value('id'), $meta);
        }

        foreach (self::PORTFOLIO_ITEMS as $slug => $meta) {
            $this->fillSeo(self::PORTFOLIO_ITEM, DB::table('portfolio_items')->where('slug', $slug)->value('id'), $meta);
        }

        $this->fillAboutShareImage();
        $this->resetAutomaticPageCanonicals();
    }

    public function down(): void
    {
        // Content only: the filled-in copy is kept, and a reset canonical
        // renders the same URL as before, so there is nothing to undo.
    }

    private function fillSetting(string $group, string $key, string $value): void
    {
        $existing = DB::table('settings')->where('group', $group)->where('key', $key)->first();

        if ($existing && filled($existing->value)) {
            return;
        }

        $existing
            ? DB::table('settings')->where('id', $existing->id)->update(['value' => $value, 'type' => 'string', 'updated_at' => now()])
            : DB::table('settings')->insert(['group' => $group, 'key' => $key, 'value' => $value, 'type' => 'string', 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array{0: string, 1: string}  $meta
     */
    private function fillSeo(string $type, mixed $id, array $meta): void
    {
        if ($id === null) {
            return;
        }

        [$title, $description] = $meta;
        $row = DB::table('seo_metadata')->where('seoable_type', $type)->where('seoable_id', $id)->first();

        if (! $row) {
            DB::table('seo_metadata')->insert([
                'seoable_type' => $type,
                'seoable_id' => $id,
                'meta_title' => $title,
                'meta_description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $changes = array_filter([
            'meta_title' => blank($row->meta_title) ? $title : null,
            'meta_description' => blank($row->meta_description) ? $description : null,
        ]);

        if ($changes !== []) {
            DB::table('seo_metadata')->where('id', $row->id)->update($changes + ['updated_at' => now()]);
        }
    }

    private function fillAboutShareImage(): void
    {
        $pageId = DB::table('pages')->where('slug', 'about')->value('id');
        $mediaId = DB::table('media')->where('path', self::ABOUT_SHARE_IMAGE_PATH)->whereNull('deleted_at')->value('id');

        if ($pageId === null || $mediaId === null) {
            return;
        }

        DB::table('seo_metadata')
            ->where('seoable_type', self::PAGE)
            ->where('seoable_id', $pageId)
            ->whereNull('og_image_media_id')
            ->update(['og_image_media_id' => $mediaId, 'updated_at' => now()]);
    }

    private function resetAutomaticPageCanonicals(): void
    {
        $rows = DB::table('seo_metadata')
            ->join('pages', 'pages.id', '=', 'seo_metadata.seoable_id')
            ->where('seo_metadata.seoable_type', self::PAGE)
            ->whereNotNull('seo_metadata.canonical_url')
            ->get(['seo_metadata.id', 'seo_metadata.canonical_url', 'pages.slug']);

        // Only this site's own host or a local development host: a
        // deliberate cross-domain canonical with the same path is kept.
        $ownHosts = array_filter([parse_url((string) config('app.url'), PHP_URL_HOST), 'localhost', '127.0.0.1']);

        foreach ($rows as $row) {
            $host = parse_url($row->canonical_url, PHP_URL_HOST);
            $path = trim((string) parse_url($row->canonical_url, PHP_URL_PATH), '/');
            $isOwnUrl = in_array($host, $ownHosts, true)
                && $path === trim($row->slug, '/')
                && parse_url($row->canonical_url, PHP_URL_QUERY) === null;

            if ($isOwnUrl) {
                DB::table('seo_metadata')->where('id', $row->id)->update(['canonical_url' => null, 'updated_at' => now()]);
            }
        }
    }
};
