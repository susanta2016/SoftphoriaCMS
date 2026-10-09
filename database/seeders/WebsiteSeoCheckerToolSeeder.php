<?php

namespace Database\Seeders;

use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Tools\Functionalities\WebsiteSeoChecker;
use Illuminate\Database\Seeder;

/**
 * The Website SEO/Metadata Pre-launch Checker's landing page (Admin →
 * Tools) as a DRAFT: content, SEO, FAQ, related tools and CTA for
 * /tools/website-seo-pre-launch-checker. Never published here — review it,
 * then publish from the admin.
 *
 * Wording rules: describe only what app/Tools/SeoChecker checks; never
 * claim it shows Google's index status, measures Core Web Vitals, renders
 * JavaScript or crawls a whole site; the score is a checklist score, not a
 * Google score; no promise of rankings or FAQ rich results. Links: only
 * tools and services that exist.
 *
 * Never overwrites an admin's work: does nothing if a tool with this slug
 * already exists.
 */
class WebsiteSeoCheckerToolSeeder extends Seeder
{
    public const SLUG = 'website-seo-pre-launch-checker';

    public const RELATED = ['image-requirements-checker', 'exact-image-kb-optimizer', 'utm-builder'];

    public function run(): void
    {
        if (Tool::query()->where('slug', self::SLUG)->exists()) {
            $this->command?->info('Website SEO/Metadata Pre-launch Checker already exists — left unchanged.');

            return;
        }

        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        $tool = new Tool([
            'name' => 'Website SEO/Metadata Pre-launch Checker',
            'slug' => self::SLUG,
            'tool_category_id' => ToolCategory::query()->where('slug', 'seo')->value('id'),
            'functionality' => WebsiteSeoChecker::KEY,
            'icon' => 'search',
            'short_description' => 'Check a page before launch for noindex tags, robots.txt blocks, canonical mistakes, missing metadata, sitemap problems and social preview issues.',
            'heading' => 'Website SEO/Metadata Pre-launch Checker',
            'introduction' => 'Check a page before your website goes live. The checker fetches it the way a search engine crawler would and reports launch blockers such as a leftover noindex tag or a robots.txt block, plus metadata, canonical, sitemap, social sharing and structure issues — with the evidence and how to fix each one.',
            'how_it_works' => <<<'HTML'
<ol>
<li><strong>Enter the address.</strong> Paste the full address of the page you want to check, usually the home page or a key landing page.</li>
<li><strong>Say what it is.</strong> Choose the live site, which should be indexable, or a staging copy, which should stay hidden. The same noindex tag is a blocker on one and expected on the other.</li>
<li><strong>Let it run.</strong> Our server fetches the page without running JavaScript, then reads the site's robots.txt and sitemap and tests a small sample of links. Most checks take 5 to 20 seconds.</li>
<li><strong>Fix the blockers first.</strong> Launch blockers are listed at the top, then warnings. Open any finding to see what was found, why it matters and how to fix it.</li>
<li><strong>Keep the report.</strong> Copy it or download it as a text file to share with your developer, then run the check again after the fixes.</li>
</ol>
HTML,
            'additional_content' => <<<'HTML'
<h2>Why Run an SEO Check Before Launch?</h2>
<p>Most launch problems that hurt search visibility are small settings, not big strategy mistakes. A staging site is hidden from search engines on purpose — with a noindex tag, a "Disallow: /" in robots.txt, or WordPress's "Discourage search engines" option — and that setting gets copied to the live site. Canonical tags still point to the staging domain. The sitemap lists old addresses. Nothing looks wrong in the browser, so nobody notices until traffic doesn't arrive.</p>
<p>Checking the page as a crawler sees it, before and right after launch, catches these problems while they are still quick to fix.</p>
<h2>What the Checker Analyses</h2>
<h3>HTTP and URL</h3>
<p>The HTTP status, every redirect on the way to the final address, whether the page uses HTTPS, whether the http:// version redirects to https://, and whether the hostname looks like a staging or temporary platform address.</p>
<h3>Metadata</h3>
<p>The page title and meta description (missing, duplicated, placeholder text, unusual length), the canonical URL and whether it loads, the language attribute, character encoding, mobile viewport and favicon.</p>
<h3>Indexability and crawling</h3>
<p>noindex and nofollow in the robots meta tag and in the X-Robots-Tag HTTP header, whether robots.txt lets Googlebot and other crawlers fetch the page, the XML sitemap with a sample of its URLs, and staging addresses left in the page's links.</p>
<h3>Social sharing</h3>
<p>The Open Graph and Twitter/X card tags, whether the share image loads and its size, and a link preview built only from the tags that were found. To size and compress a share image, use the <a href="/tools/image-requirements-checker">Image Requirements Checker</a> and the <a href="/tools/exact-image-kb-optimizer">Exact Image KB Optimizer</a>.</p>
<h3>On-page structure</h3>
<p>H1 and heading order, images without alt text, internal and external links with a small sample tested for errors, structured data (JSON-LD syntax) and how much text is in the HTML.</p>
<h3>Server response</h3>
<p>How long the server took to send the HTML, its size and whether it is compressed. This is not a page-speed test: images, scripts and Core Web Vitals are not measured.</p>
<h2>How to Read the Report</h2>
<ul>
<li><strong>Launch blockers</strong> can stop the page from being crawled, indexed or trusted — for example noindex on the live site, a robots.txt block, an error status or a canonical pointing to staging. Fix these before launch.</li>
<li><strong>Warnings</strong> are worth fixing but won't stop the page from being found, such as a missing meta description or images without alt text.</li>
<li><strong>Passed</strong> checks show what was found, so you can confirm it is what you intended.</li>
<li><strong>Not checked</strong> means a check couldn't run, for example because a file didn't respond in time. It isn't counted in the score.</li>
</ul>
<p>The checklist score weighs launch-critical checks most and is capped at 59 while any blocker remains. It describes these checks only: it is not a Google score and doesn't predict rankings.</p>
<h2>Common Pre-launch SEO Mistakes</h2>
<ul>
<li><strong>noindex left on.</strong> The staging setting is copied to the live site, so search engines are told to ignore it.</li>
<li><strong>robots.txt blocks everything.</strong> "Disallow: /" from staging stops crawling of the whole site.</li>
<li><strong>Canonical and og:url point to staging.</strong> Search engines and social apps are sent to the wrong domain.</li>
<li><strong>Sitemap with old or staging addresses.</strong> The sitemap is generated before the domain changes and never regenerated.</li>
<li><strong>http:// and https:// both load.</strong> Without a redirect there are two copies of every page.</li>
<li><strong>Duplicate or placeholder titles.</strong> "Home", "Just another WordPress site" or the same title on every page.</li>
<li><strong>Missing share image.</strong> Links shared on launch day show no picture.</li>
</ul>
<h2>Website Launch Checklist</h2>
<ol>
<li>Remove password protection, noindex tags and "Discourage search engines" settings from the live site.</li>
<li>Replace the staging robots.txt and make sure it lists the sitemap.</li>
<li>Point the domain to the new site, force HTTPS and redirect http:// and www/non-www variants with a 301.</li>
<li>Search and replace the staging domain in the database and settings, then clear every cache.</li>
<li>Redirect old URLs that changed to their new addresses with a 301.</li>
<li>Regenerate the XML sitemap and submit it in Google Search Console.</li>
<li>Check titles, descriptions, canonicals and share images on your key pages.</li>
<li>Run this checker on the live home page and key landing pages, and fix any blockers.</li>
<li>Install analytics and tag launch campaigns with the <a href="/tools/utm-builder">UTM Builder</a>.</li>
<li>Test real page speed with PageSpeed Insights, and request indexing of key pages in Search Console.</li>
</ol>
<h2>When to Ask for Professional Help</h2>
<p>Ask a developer when a blocker comes from server or hosting configuration (headers, redirects, HTTPS), when you are moving a site with many existing URLs, when content is rendered by JavaScript, or when the same problem appears on many pages. Softphoria builds and launches <a href="/services/web-development">websites</a> and <a href="/services/cms-content-platforms">content platforms</a> with technical SEO built in, and offers <a href="/services/digital-marketing">technical SEO and digital marketing</a> for existing sites.</p>
HTML,
            'important_notes' => <<<'HTML'
<p>One page is checked per run, plus the site's robots.txt, sitemap and a small sample of links. The page is read as raw HTML without running JavaScript, so metadata added by scripts isn't seen.</p>
<p>The results show whether search engines are allowed to crawl and index the page — not whether Google has indexed it; use Google Search Console for that. Server response time is one measurement from our server, not a page-speed or Core Web Vitals test. The address you enter and the report are not stored.</p>
HTML,
            'cta_heading' => 'Launching or Redesigning a Website?',
            'cta_text' => 'Softphoria builds fast, search-ready websites and fixes technical SEO problems before they cost you traffic — from metadata and redirects to WordPress performance.',
            'cta_label' => 'Discuss your launch',
            'cta_url' => '/contact',
        ]);
        $tool->status = ToolStatus::Draft;
        $tool->created_by = $actor?->getKey();
        $tool->updated_by = $actor?->getKey();
        $tool->save();

        $tool->seo()->create([
            'meta_title' => 'Website SEO Checker & Pre-Launch Audit | Softphoria',
            'meta_description' => 'Check your website before launch for noindex, robots.txt, canonical URLs, sitemap, metadata and social preview issues. Get clear, actionable fixes free.',
        ]);

        foreach (self::faqs() as $order => [$question, $answer]) {
            $tool->faqs()->create(['question' => $question, 'answer' => $answer, 'is_visible' => true, 'sort_order' => $order]);
        }

        // Related tools: only ones that exist, in this order.
        $related = Tool::query()->whereIn('slug', self::RELATED)->pluck('id', 'slug');
        foreach (self::RELATED as $order => $slug) {
            if ($related->has($slug)) {
                $tool->relatedTools()->attach($related[$slug], ['sort_order' => $order]);
            }
        }

        $this->command?->info('Website SEO/Metadata Pre-launch Checker created as a draft. Review and publish it in Admin → Tools.');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function faqs(): array
    {
        return [
            ['What does a website SEO pre-launch checker do?', 'It looks at a page the way a search engine crawler first sees it and reports anything that could stop it from being crawled, indexed or shown well in results: the HTTP status and redirects, noindex tags and headers, robots.txt rules, the canonical URL, the sitemap, titles and descriptions, social sharing tags, headings, images and links. Each finding shows what was found and how to fix it.'],
            ['Can it detect a staging website accidentally set to noindex?', 'Yes. It reads the robots meta tag and the X-Robots-Tag HTTP header, and checks robots.txt for rules that block the page. If you check the live site, noindex or a robots.txt block is reported as a launch blocker. If you choose "staging or pre-launch copy", the same settings are reported as expected, with a reminder to remove them at launch.'],
            ['Does the tool tell me whether Google has indexed my website?', 'No. It shows whether search engines are allowed to crawl and index the page at the moment of the check. Whether Google has actually indexed it is only known to Google: use the URL Inspection tool in Google Search Console, which can also request indexing.'],
            ['Does a missing meta description hurt rankings?', 'Not directly — Google says the meta description isn\'t a ranking factor. It is often used as the snippet under your title in search results, though, so a clear description can earn more clicks. Without one, search engines pick text from the page, which may be less helpful. Google can also rewrite the snippet even when you have one.'],
            ['Why is my canonical URL important?', 'The canonical URL tells search engines which address is the main version of a page, so copies reached through tracking parameters, http and https, or www and non-www are combined instead of competing. After a launch, canonicals that still point to a staging domain or a page that doesn\'t load are a common, serious mistake, which is why the checker tests both.'],
            ['Does the checker analyse JavaScript-rendered metadata?', 'No. It reads the HTML the server sends and doesn\'t run JavaScript, so titles, tags or content added by scripts are not seen. Search engines can render JavaScript, but often later and not always completely, so important metadata is safest in the server\'s HTML. If the checker finds very little text, the page is probably rendered by JavaScript.'],
            ['Can it check a complete website or only one page?', 'One page per check, plus site-wide files that affect it: robots.txt, the XML sitemap (with a few of its URLs tested) and a small sample of the page\'s links. Run it on your home page and key landing pages. A full-site crawl needs a dedicated crawler.'],
            ['What should I fix before launching a website?', 'Fix the launch blockers first: remove noindex and robots.txt blocks from the live site, make sure the page returns HTTP 200 over HTTPS, and point canonicals at the live domain. Then set unique titles and descriptions, a share image, one clear H1 and image alt text, and regenerate the sitemap. The checklist on this page lists the usual launch steps.'],
            ['Does a high score guarantee search rankings?', 'No. The score only summarises these technical checks. Rankings depend on content, relevance, links, competition and much more, and even a well-configured page isn\'t guaranteed to be indexed. A high score means the basics checked here are in place.'],
            ['When should I ask a developer for help?', 'When a blocker comes from server or hosting settings such as headers, redirects or HTTPS, when you are migrating a site with many existing URLs, when your content is rendered by JavaScript, or when the same issue affects many pages. Softphoria can review and fix these before or after launch.'],
        ];
    }
}
