<?php

namespace Database\Seeders;

use App\Enums\ToolStatus;
use App\Models\Service;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Tools\Functionalities\UtmBuilder;
use Illuminate\Database\Seeder;

/**
 * The UTM Builder's landing page (Admin → Tools) as a DRAFT: content, SEO,
 * FAQ, related tools, related service and CTA for /tools/utm-builder. It is
 * never published here — review it, then publish from the admin.
 *
 * The live tool was created in the admin; this seeder records its content
 * (SEO update of 2026-10-09) so a fresh install matches it. Google
 * Analytics statements follow support.google.com/analytics/answer/10917952
 * (checked 2026-10-09). Examples use the reserved example.com domain and
 * made-up campaigns. Related tools and service: only ones that exist.
 *
 * Never overwrites an admin's work: does nothing if a tool with this slug
 * already exists.
 */
class UtmBuilderToolSeeder extends Seeder
{
    public const SLUG = 'utm-builder';

    public function run(): void
    {
        if (Tool::query()->where('slug', self::SLUG)->exists()) {
            $this->command?->info('UTM Builder already exists — left unchanged.');

            return;
        }

        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        $tool = new Tool([
            'name' => 'UTM Builder',
            'slug' => self::SLUG,
            'tool_category_id' => ToolCategory::query()->where('slug', 'marketing')->value('id'),
            'functionality' => UtmBuilder::KEY,
            'icon' => 'megaphone',
            'short_description' => 'Build campaign URLs with UTM parameters so every visit from your ads, emails and posts shows up correctly in analytics.',
            'heading' => 'Free UTM Builder: Create Google Analytics Campaign URLs',
            'introduction' => 'Add your page address and campaign details to get a tagged link for your emails, social posts and ads. The link is built in your browser and is not sent to Softphoria.',
            'how_it_works' => <<<'HTML'
<ol>
<li><strong>Enter the website URL.</strong> The page people should land on. Existing parameters and a #section are kept.</li>
<li><strong>Fill in the three essential fields.</strong> Source (such as newsletter), medium (such as email) and campaign name (such as spring_sale).</li>
<li><strong>Add optional details.</strong> A paid search term, content to tell links apart, or a campaign ID.</li>
<li><strong>Check the campaign URL.</strong> It updates as you type. Keep lowercase on, so "Email" and "email" are not two sources.</li>
<li><strong>Copy and use it.</strong> Paste the link into your email, post or ad. Clear starts a new one.</li>
</ol>
HTML,
            'additional_content' => <<<'HTML'
<h2>What are UTM parameters?</h2>
<p>UTM parameters are short tags added to the end of a link. They do not change the page; they tell your analytics tool where a visit came from.</p>
<ul>
<li><strong>What they record.</strong> The source (such as newsletter), medium (such as email) and campaign (such as spring_sale) of each visit.</li>
<li><strong>Why use them.</strong> Clicks from emails and messaging apps are often reported as direct visits. Tags let you compare channels and campaigns.</li>
<li><strong>Where to see them.</strong> In Google Analytics 4, in the Traffic acquisition report as session source, medium and campaign.</li>
</ul>
<p>A tagged link looks like this; each <code>utm_</code> pair after the <code>?</code> is one tag:</p>
<pre><code>https://www.example.com/pricing?utm_source=newsletter&amp;utm_medium=email&amp;utm_campaign=spring_sale</code></pre>
<h2>UTM parameters explained</h2>
<p>The builder supports the six standard UTM parameters. Google says to always use source, medium and campaign when you tag a link; a missing parameter shows as "(not set)" in reports.</p>
<table>
<thead><tr><th>Parameter</th><th>What it identifies</th><th>Example</th><th>Needed?</th></tr></thead>
<tbody>
<tr><td><code>utm_source</code></td><td>Where the traffic comes from: a search engine, platform, newsletter or partner site</td><td><code>google</code>, <code>newsletter</code>, <code>linkedin</code></td><td>Always set</td></tr>
<tr><td><code>utm_medium</code></td><td>The type of channel</td><td><code>cpc</code>, <code>email</code>, <code>social</code></td><td>Always set</td></tr>
<tr><td><code>utm_campaign</code></td><td>The campaign, promotion or product</td><td><code>spring_sale</code></td><td>Always set</td></tr>
<tr><td><code>utm_term</code></td><td>The paid search keyword</td><td><code>website_design</code></td><td>Optional</td></tr>
<tr><td><code>utm_content</code></td><td>Which link or ad was clicked when a campaign has several, such as a banner or a text link</td><td><code>hero_button</code></td><td>Optional</td></tr>
<tr><td><code>utm_id</code></td><td>The campaign ID</td><td><code>spring_2026_01</code></td><td>Optional; recommended by Google</td></tr>
</tbody>
</table>
<p><strong>Campaign ID (utm_id).</strong> Links work and are reported without it, so it is optional for ordinary link tagging. Google still recommends setting it, and asks you to use the same IDs as in any campaign data you upload to Google Analytics, so the uploaded data can be matched to your visits. Keep one ID per campaign and reuse it on every link in that campaign.</p>
<p><strong>Source platform (utm_source_platform).</strong> Google Analytics also supports utm_source_platform, the platform that directs the traffic, such as Search Ads 360 or Display &amp; Video 360, and Google recommends setting it as well. The builder has no field for it: if you need it, include it in the website URL and it is kept as it is.</p>
<h2>UTM naming conventions and best practices</h2>
<ul>
<li><strong>Use lowercase, always.</strong> Values are case-sensitive: <code>utm_source=Facebook</code> and <code>utm_source=facebook</code> are reported as two different sources. The lowercase option does this for you.</li>
<li><strong>Keep source and medium apart.</strong> The source is where the link appears (facebook, newsletter, google); the medium is the kind of channel (social, email, cpc). Do not swap them or combine them, as in facebook_social.</li>
<li><strong>Use common medium values.</strong> Google Analytics sorts visits into channels such as Email, Organic Social and Paid Search partly from the source and medium, so standard values like email, social and cpc are grouped correctly.</li>
<li><strong>Name campaigns one way.</strong> Choose a pattern, such as spring_sale_2026, write it down and have everyone on the team use it.</li>
<li><strong>Pick one separator.</strong> Use underscores or hyphens rather than spaces. With the lowercase option on, the builder turns spaces into underscores; with it off, spaces are encoded as %20.</li>
<li><strong>Type values as plain text.</strong> The builder URL-encodes characters such as &amp;, ?, # and /, so they cannot break the link. Typing %20 yourself would be encoded again.</li>
<li><strong>Existing parameters stay.</strong> Anything already in the website URL is kept without being re-encoded, and a #section stays at the end. If the address already has a UTM parameter, filling in that field replaces it; a blank optional field keeps the existing value.</li>
<li><strong>Do not tag internal links.</strong> Use UTM links only from other places, such as emails, posts, ads and partner sites. A UTM link from one page of your site to another can disrupt attribution, crediting your own link instead of the campaign that brought the visitor.</li>
<li><strong>The builder only creates links.</strong> It does not collect any data. Results appear in your own analytics tool, such as Google Analytics, when people click your links, and only if that tool is installed on the destination site.</li>
</ul>
<h2>UTM examples for email, social media and paid ads</h2>
<p>These examples use the reserved example.com domain and made-up campaigns. Replace the values with your own.</p>
<h3>Email newsletter</h3>
<pre><code>https://www.example.com/spring-sale?utm_source=newsletter&amp;utm_medium=email&amp;utm_campaign=spring_sale_2026&amp;utm_content=header_button</code></pre>
<p><code>utm_source=newsletter</code> says the link is in your newsletter, <code>utm_medium=email</code> names the channel and <code>utm_campaign=spring_sale_2026</code> the promotion. <code>utm_content=header_button</code> tells this link apart from a second link to the same page further down the email.</p>
<h3>Social media post</h3>
<pre><code>https://www.example.com/blog/product-launch?utm_source=linkedin&amp;utm_medium=social&amp;utm_campaign=product_launch&amp;utm_content=carousel_post</code></pre>
<p><code>utm_source=linkedin</code> is the platform and <code>utm_medium=social</code> marks an organic post. <code>utm_campaign=product_launch</code> groups every post about the launch, and <code>utm_content=carousel_post</code> separates this post from others in the campaign. When you share a link, the preview image comes from the page's Open Graph tags: see <a href="/tools/open-graph-image-size">Open Graph image size</a>, and <a href="/tools/social-media-image-sizes">social media image sizes</a> for the post itself.</p>
<h3>Paid search ad</h3>
<pre><code>https://www.example.com/web-design?utm_source=bing&amp;utm_medium=cpc&amp;utm_campaign=web_design_services&amp;utm_term=website_design&amp;utm_id=ms_2026_014</code></pre>
<p><code>utm_source=bing</code> is the search engine and <code>utm_medium=cpc</code> marks a paid click. <code>utm_campaign=web_design_services</code> is the ad campaign, <code>utm_term=website_design</code> the keyword and <code>utm_id=ms_2026_014</code> the campaign's ID. Google Ads with auto-tagging turned on passes this information to Google Analytics itself, so manual UTM links are mainly useful for other ad platforms.</p>
HTML,
            'cta_heading' => 'Need help with digital marketing?',
            'cta_text' => 'Get help with campaign tracking, analytics setup and digital marketing strategy.',
            'cta_label' => 'See digital marketing',
            'cta_url' => '/services/digital-marketing',
        ]);
        $tool->service_id = Service::query()->where('slug', 'digital-marketing')->value('id');
        $tool->status = ToolStatus::Draft;
        $tool->created_by = $actor?->getKey();
        $tool->updated_by = $actor?->getKey();
        $tool->save();

        $tool->seo()->create([
            'meta_title' => 'Free UTM Builder — Campaign URL Generator | Softphoria',
            'meta_description' => 'Build free UTM tracking URLs for Google Analytics. Add campaign source, medium, name and optional parameters to track email, social media and ad campaigns.',
        ]);

        foreach (self::faqs() as $order => [$question, $answer]) {
            $tool->faqs()->create(['question' => $question, 'answer' => $answer, 'is_visible' => true, 'sort_order' => $order]);
        }

        // Related tools: only ones that exist, in this order.
        $related = Tool::query()->whereIn('slug', ['image-requirements-checker', 'social-video-safe-zone-checker'])->pluck('id', 'slug');
        foreach (['image-requirements-checker', 'social-video-safe-zone-checker'] as $order => $slug) {
            if ($related->has($slug)) {
                $tool->relatedTools()->attach($related[$slug], ['sort_order' => $order]);
            }
        }

        $this->command?->info('UTM Builder created as a draft. Review and publish it in Admin → Tools.');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function faqs(): array
    {
        return [
            ['What is a UTM builder?', 'A tool that adds UTM parameters to a web address for you, so you get a correctly formatted and encoded campaign URL without editing the query string by hand. This one also keeps any parameters already in the address.'],
            ['Is this UTM builder free?', 'Yes. It is free, needs no sign-up and has no limit on the number of links. Links are built in your browser and are not sent to Softphoria.'],
            ['Which UTM parameters are required?', 'A link works with or without UTM parameters, but for useful reports always set utm_source, utm_medium and utm_campaign, as Google advises; a missing one shows as "(not set)". utm_term, utm_content and utm_id are optional, although Google also recommends utm_id. The builder asks for the three essential fields.'],
            ['What is the difference between utm_source and utm_medium?', 'utm_source is where the traffic comes from, such as google, facebook or newsletter. utm_medium is the type of channel, such as cpc, social or email. A paid Facebook ad and an ordinary Facebook post share the source facebook but have different mediums, such as cpc and social.'],
            ['How do I use UTM links in Google Analytics 4?', 'Use the tagged link wherever you share it: in emails, posts, ads or on partner sites. When people click it, Google Analytics 4 records the values. To see them, open the Traffic acquisition report under Acquisition and choose Session source / medium or Session campaign. Google Analytics must be installed on the destination page, and new data can take a day or two to appear in standard reports.'],
            ['Are UTM parameters case-sensitive?', 'Yes, their values are. Google Analytics reports utm_source=Facebook and utm_source=facebook as two different sources, so use lowercase consistently. Leave the builder\'s lowercase option on to do this automatically.'],
            ['Can I use UTM parameters on existing URLs with query strings?', 'Yes. Paste the full address: the builder adds the UTM parameters after the existing ones without re-encoding them, and keeps a #section at the end. If the address already contains a UTM parameter, filling in that field replaces it, and a blank optional field keeps the existing value.'],
        ];
    }
}
