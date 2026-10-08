<?php

namespace Database\Seeders;

use App\Actions\Page\CreatePageAction;
use App\Enums\BlogPostStatus;
use App\Enums\PageSectionType;
use App\Enums\PageTemplate;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The Social Video Safe Zone Checker's SEO content cluster (Phase 1 of the
 * approved SEO/content spec, 2026-10-08), all as DRAFTS for review:
 *
 *   /tools/instagram-reels-safe-zone           tool-guide CMS Page
 *   /tools/youtube-shorts-safe-zone            tool-guide CMS Page
 *   /tools/1080x1920-safe-zone-guide           tool-guide CMS Page
 *   /blog/why-social-media-safe-zone-numbers-differ   blog post
 *
 * Wording rules (spec): every value is an observed P0 measurement, never an
 * official specification; single-device observations name their device;
 * the combined Reels + Shorts area never includes TikTok; the 672 px ad
 * figures are described as ad guidance, never as wrong. The margins and the
 * overlay PNGs come from the checker's dataset (see
 * resources/js/tools/social-video-safe-zone/scripts/generate-overlays.mjs,
 * whose test fails if these numbers and that data ever disagree).
 *
 * Author: Softphoria (the organisation), so no person is set as author.
 * Never overwrites: a slug that already exists is left untouched.
 */
class SocialVideoSafeZoneGuidesSeeder extends Seeder
{
    public const HUB = '/tools/social-video-safe-zone-checker';

    public const REELS = 'instagram-reels-safe-zone';

    public const SHORTS = 'youtube-shorts-safe-zone';

    public const COMBINED = '1080x1920-safe-zone-guide';

    public const ARTICLE = 'why-social-media-safe-zone-numbers-differ';

    private const DOWNLOADS = '/downloads/social-video-safe-zone';

    public function run(): void
    {
        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        if (! $actor) {
            return;
        }

        foreach ($this->guides() as $guide) {
            if (Page::withTrashed()->where('slug', $guide['slug'])->exists()) {
                $this->command?->info("/tools/{$guide['slug']} already exists — left unchanged.");

                continue;
            }

            $page = app(CreatePageAction::class)->handle($guide, $actor);
            // Organisation authorship: no person is named as the author.
            $page->forceFill(['author_id' => null])->save();
            $this->command?->info("Created /tools/{$guide['slug']} as a draft.");
        }

        if (BlogPost::query()->where('slug', self::ARTICLE)->exists()) {
            $this->command?->info('/blog/'.self::ARTICLE.' already exists — left unchanged.');

            return;
        }

        $post = BlogPost::query()->create([
            'title' => 'Why Every Safe-Zone Guide Gives Different Numbers',
            'slug' => self::ARTICLE,
            'excerpt' => 'Bottom margins for the same 1080×1920 video are quoted from about 190 px to 672 px. Here is where each kind of number comes from, and when to use it.',
            'body' => $this->articleBody(),
            'status' => BlogPostStatus::Draft,
            'author_id' => null,
            'created_by' => $actor->getKey(),
            'updated_by' => $actor->getKey(),
        ]);
        $post->seo()->create([
            'meta_title' => 'Why Social Video Safe-Zone Numbers Differ | Softphoria',
            'meta_description' => 'Bottom margins for the same 1080×1920 video are quoted from about 190 px to 672 px. Here is where each kind of number comes from and when to use it.',
        ]);
        $this->command?->info('Created /blog/'.self::ARTICLE.' as a draft.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function guides(): array
    {
        return [
            [
                'title' => 'Instagram Reels Safe Zone: Where the App Covers Your Video',
                'slug' => self::REELS,
                'template' => PageTemplate::Standard->value,
                'is_tool_guide' => true,
                'summary' => 'Measured on real phones: where the Instagram Reels interface covers a 1080 × 1920 video, and how much of each edge to keep clear.',
                'sections' => [$this->richText($this->reelsBody()), $this->cta('Check your Reel against these zones')],
                'seo' => [
                    'meta_title' => 'Instagram Reels Safe Zone (1080×1920): Measured Margins',
                    'meta_description' => 'Observed on real phones in October 2026: keep the top 190 px, right 160 px and bottom 220 px of a 1080×1920 Reel clear, plus the caption and auto-caption areas.',
                ],
            ],
            [
                'title' => 'YouTube Shorts Safe Zone: Where the App Covers Your Video',
                'slug' => self::SHORTS,
                'template' => PageTemplate::Standard->value,
                'is_tool_guide' => true,
                'summary' => 'Measured on real phones: where the YouTube Shorts interface covers a 1080 × 1920 video, and how much of each edge to keep clear.',
                'sections' => [$this->richText($this->shortsBody()), $this->cta('Check your Short against these zones')],
                'seo' => [
                    'meta_title' => 'YouTube Shorts Safe Zone (1080×1920): Measured Margins',
                    'meta_description' => 'Observed on real phones in October 2026: keep the top 180 px, right 170 px and bottom 190 px of a 1080×1920 Short clear, plus the auto-caption band at the top.',
                ],
            ],
            [
                'title' => '1080×1920 Safe Zone Guide for Reels & Shorts',
                'slug' => self::COMBINED,
                'template' => PageTemplate::Standard->value,
                'is_tool_guide' => true,
                'summary' => 'One set of margins for a 1080 × 1920 video you post to both Instagram Reels and YouTube Shorts, derived from our measured interface zones.',
                'sections' => [$this->richText($this->combinedBody()), $this->cta('Check one video against Reels and Shorts at once')],
                'seo' => [
                    'meta_title' => '1080×1920 Safe Zone Guide for Reels & Shorts | Softphoria',
                    'meta_description' => 'The combined Reels + Shorts safe area for 1080×1920: keep the top 190 px, right 170 px and bottom 220 px clear. Derived from our measured interface zones.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function richText(string $body): array
    {
        return ['section_type' => PageSectionType::RichText->value, 'title' => null, 'is_enabled' => true, 'content_json' => ['body' => $body]];
    }

    /**
     * @return array<string, mixed>
     */
    private function cta(string $heading): array
    {
        return [
            'section_type' => PageSectionType::Cta->value,
            'title' => 'Check your video',
            'is_enabled' => true,
            'content_json' => [
                'eyebrow' => 'Free tool',
                'heading' => $heading,
                'description' => 'Upload a vertical video or image. The checker finds text and logos in your own frames, shows what the app interface covers and suggests how far to move them. It runs in your browser; nothing is uploaded.',
                'cta_label' => 'Open the Safe Zone Checker',
                'cta_url' => self::HUB,
            ],
        ];
    }

    private function figure(string $file, string $alt, string $caption): string
    {
        return '<figure><img src="'.self::DOWNLOADS."/{$file}-preview.png\" alt=\"{$alt}\" width=\"324\" height=\"576\" loading=\"lazy\"><figcaption>{$caption}</figcaption></figure>";
    }

    private function download(string $file, string $label): string
    {
        return '<p><a href="'.self::DOWNLOADS."/{$file}.png\" download>{$label}</a> (PNG, 1080 × 1920, transparent). Put it on the top layer in CapCut, Premiere Pro, DaVinci Resolve or Canva and hide it before you export. It shows the short-caption layout with auto-captions off.</p>";
    }

    /**
     * The shared "How we measured" block (spec section M).
     *
     * @param  array<int, string>  $devices
     */
    private function howWeMeasured(string $apps, array $devices, string $platformName): string
    {
        $phones = implode(' and ', $devices);

        return <<<HTML
<h2>How we measured</h2>
<p>We made calibration videos at 1080 × 1920, played them in the official {$apps} and took screenshots on real phones. Each interface element's edges were measured on the frame and recorded to the nearest 10 px. Where the test phones differed, the larger covered area is used.</p>
<ul>
<li><strong>Test phones:</strong> {$phones}, with the app versions current at the time of measurement.</li>
<li><strong>Last verified:</strong> October 2026. We re-check quarterly, or sooner when a visible interface change is reported.</li>
<li><strong>Observed, not official:</strong> these are our measurements of the {$platformName} interface, not an official platform specification. They are practical guidance.</li>
<li><strong>Variation:</strong> interfaces can vary by device, app version, account configuration and interface experiments. Longer captions and taller screens can cover more.</li>
<li><strong>Measured and maintained by Softphoria.</strong> <a href="/about">About Softphoria</a> · <a href="/contact">Tell us if your app looks different</a></li>
</ul>
HTML;
    }

    private function reelsBody(): string
    {
        $figure = $this->figure('softphoria-instagram-reels-safe-zone-1080x1920', 'Instagram Reels safe zones on a 1080 by 1920 frame: the top bar, the button column on the right and the caption and audio row at the bottom, with the safe area outlined in green', 'Instagram Reels zones on a 1080 × 1920 frame (short caption, auto-captions off). Grey hatched edges are cut off on some taller screens.');
        $download = $this->download('softphoria-instagram-reels-safe-zone-1080x1920', 'Download the Instagram Reels safe-zone overlay');
        $measured = $this->howWeMeasured('Instagram app', ['iPhone 14 Plus (iOS)', 'Nokia 6.1 Plus (Android)'], 'Instagram Reels');
        $hub = self::HUB;
        $combined = '/tools/'.self::COMBINED;
        $article = '/blog/'.self::ARTICLE;

        return <<<HTML
<h2>Measured Instagram Reels safe zone</h2>
<p>On a 1080 × 1920 Reel, keep important text, faces and logos out of the top 190 px, the right 160 px and the bottom 220 px. Those areas sit under Instagram's own interface while a Reel plays with a short caption.</p>
<table>
<thead><tr><th>Edge</th><th>Keep clear</th><th>What covers it</th></tr></thead>
<tbody>
<tr><td>Top</td><td>190 px</td><td>Status bar and Instagram's top bar</td></tr>
<tr><td>Right</td><td>160 px</td><td>Like, comment and share buttons</td></tr>
<tr><td>Bottom</td><td>220 px</td><td>Username, caption and audio tile (caption collapsed)</td></tr>
</tbody>
</table>
{$figure}
<p><strong>About these numbers:</strong> 1080 × 1920 reference canvas · measured on an iPhone 14 Plus and a Nokia 6.1 Plus · last verified October 2026 · observed measurements, not an official Instagram specification · interfaces can vary by device, app version, account configuration and interface experiments.</p>
<p><a href="{$hub}">Check your Reel against these zones</a> with the free Social Video Safe Zone Checker. It finds text and logos in your own video, shows what Instagram covers and suggests how far to move them.</p>
<h2>What covers each edge</h2>
<ul>
<li><strong>Top, 190 px:</strong> the phone's status bar and Instagram's top bar. The bar was taller on the iPhone than on the Nokia; 190 px covers both.</li>
<li><strong>Right, 160 px:</strong> the like, comment, share and more buttons, a column that runs from about y 1120 down to the caption row.</li>
<li><strong>Bottom, 220 px:</strong> the username and caption, which start at y 1700, and the audio tile at the bottom right (observed on the Nokia 6.1 Plus). The last 20 px hold the thin progress bar.</li>
</ul>
<h2>When the caption is opened</h2>
<p>When a viewer taps a long caption to read it, the caption panel grows upward. On the Nokia 6.1 Plus it covered up to 750 px from the bottom of the frame (from y 1170). On the iPhone 14 Plus, the Instagram version we tested shrank the video instead of covering it. The opened caption only appears when a viewer asks for it, so the checker treats it as a lower-risk area, but keep anything essential above it if your captions are long.</p>
<h2>Auto-captions</h2>
<p>With Instagram's auto-captions switched on, the captions appeared in a band from y 1590 to y 1700, just above the caption area (observed on the iPhone 14 Plus; the Instagram version on our Android test phone had no captions option). If you burn in your own subtitles, keep them out of this band, or viewers who use auto-captions will see two lines of text on top of each other.</p>
<h2>Organic Reels and Reels ads</h2>
<p><a href="https://www.facebook.com/business/ads-guide/update/video/instagram-reels">Meta's Ads Guide</a> advises leaving at least 14% of the top, 35% of the bottom and 6% of each side of a Reels ad free from text and logos. On a 1920 px tall video, 35% is 672 px. That guidance is for ads, which carry extra interface such as a call-to-action button. The 220 px above is what we observed on ordinary, organic Reels. For ads, follow Meta's guidance; for organic posts, these measurements are a practical guide. <a href="{$article}">Why safe-zone numbers differ</a> explains the gap.</p>
<h2>Taller phones and the side edges</h2>
<p>A 1080 × 1920 video is 9:16, but many phones have taller screens. On the iPhone 14 Plus, Instagram filled the screen height and cut off a strip at each side: about 54 px on the left and 53 px on the right. Keep text a little way in from the side edges too. The overlay marks these strips with grey hatching.</p>
{$measured}
<h2>Download the Instagram Reels overlay</h2>
{$download}
<p>A template shows where the zones are; the checker checks your actual frames. Posting the same video to YouTube Shorts as well? Use the <a href="{$combined}">1080×1920 safe zone guide for Reels &amp; Shorts</a>.</p>
HTML;
    }

    private function shortsBody(): string
    {
        $figure = $this->figure('softphoria-youtube-shorts-safe-zone-1080x1920', 'YouTube Shorts safe zones on a 1080 by 1920 frame: the top bar, the button column on the right and the title and channel row at the bottom, with the safe area outlined in green', 'YouTube Shorts zones on a 1080 × 1920 frame (short caption, auto-captions off). Grey hatched edges are cut off on some taller screens.');
        $download = $this->download('softphoria-youtube-shorts-safe-zone-1080x1920', 'Download the YouTube Shorts safe-zone overlay');
        $measured = $this->howWeMeasured('YouTube app', ['iPhone 14 Plus (iOS)', 'Samsung Galaxy A20s (Android)'], 'YouTube Shorts');
        $hub = self::HUB;
        $combined = '/tools/'.self::COMBINED;
        $article = '/blog/'.self::ARTICLE;

        return <<<HTML
<h2>Measured YouTube Shorts safe zone</h2>
<p>On a 1080 × 1920 Short, keep important text, faces and logos out of the top 180 px, the right 170 px and the bottom 190 px. Those areas sit under YouTube's own interface while a Short plays.</p>
<table>
<thead><tr><th>Edge</th><th>Keep clear</th><th>What covers it</th></tr></thead>
<tbody>
<tr><td>Top</td><td>180 px</td><td>Status bar and YouTube's top bar</td></tr>
<tr><td>Right</td><td>170 px</td><td>Like, comment and share buttons</td></tr>
<tr><td>Bottom</td><td>190 px</td><td>Channel name and title</td></tr>
</tbody>
</table>
{$figure}
<p><strong>About these numbers:</strong> 1080 × 1920 reference canvas · measured on an iPhone 14 Plus and a Samsung Galaxy A20s · last verified October 2026 · observed measurements, not an official YouTube specification · interfaces can vary by device, app version, account configuration and interface experiments.</p>
<p><a href="{$hub}">Check your Short against these zones</a> with the free Social Video Safe Zone Checker. It finds text and logos in your own video, shows what YouTube covers and suggests how far to move them.</p>
<h2>What covers each edge</h2>
<ul>
<li><strong>Top, 180 px:</strong> the phone's status bar and YouTube's top bar. The bar was slightly taller on the iPhone than on the Galaxy A20s; 180 px covers both.</li>
<li><strong>Right, 170 px:</strong> the button column, which runs from about y 1060 down to the bottom row.</li>
<li><strong>Bottom, 190 px:</strong> the channel name and the Short's title, which start at y 1730.</li>
</ul>
<h2>Auto-captions sit near the top</h2>
<p>Unlike Instagram, YouTube placed its auto-captions near the top of the frame: in a band from y 260 to y 380 on our test phones. If you burn in your own titles or subtitles there, viewers who switch captions on will see them overlap.</p>
<h2>Organic Shorts and vertical video ads</h2>
<p>You will often see 672 px quoted as the bottom margin for Shorts. That figure comes from <a href="https://support.google.com/google-ads/answer/9128498?hl=en">Google's help page for vertical video ads</a>, which can run in-stream, in-feed and in Shorts. Its 1080 × 1920 reference image marks a safe zone 288 px from the top, 672 px from the bottom, 48 px from the left and 192 px from the right (checked 8 October 2026). That is ad guidance covering several placements, each with extra ad interface, so it is deliberately cautious. The 190 px above is what we observed on ordinary, organic Shorts. For ads, follow Google's guidance; for organic posts, these measurements are a practical guide. <a href="{$article}">Why safe-zone numbers differ</a> explains the gap in more detail.</p>
<h2>Taller phones and the side edges</h2>
<p>A 1080 × 1920 video is 9:16, but many phones have taller screens. On the iPhone 14 Plus, YouTube cut off about 55 px on the left and 53 px on the right; on the Galaxy A20s the strips were narrower. Keep text a little way in from the side edges too. The overlay marks these strips with grey hatching.</p>
{$measured}
<h2>Download the YouTube Shorts overlay</h2>
{$download}
<p>A template shows where the zones are; the checker checks your actual frames. Posting the same video to Instagram Reels as well? Use the <a href="{$combined}">1080×1920 safe zone guide for Reels &amp; Shorts</a>.</p>
HTML;
    }

    private function combinedBody(): string
    {
        $figure = $this->figure('softphoria-reels-shorts-combined-safe-zone-1080x1920', 'Combined Instagram Reels and YouTube Shorts zones on a 1080 by 1920 frame, with the combined safe area outlined in green', 'Instagram Reels and YouTube Shorts zones together on a 1080 × 1920 frame (short caption, auto-captions off). TikTok is not included.');
        $download = $this->download('softphoria-reels-shorts-combined-safe-zone-1080x1920', 'Download the combined Reels + Shorts safe-zone overlay');
        $measured = $this->howWeMeasured('Instagram and YouTube apps', ['iPhone 14 Plus (iOS)', 'Nokia 6.1 Plus (Android, Instagram)', 'Samsung Galaxy A20s (Android, YouTube)'], 'Instagram Reels and YouTube Shorts');
        $hub = self::HUB;
        $reels = '/tools/'.self::REELS;
        $shorts = '/tools/'.self::SHORTS;
        $article = '/blog/'.self::ARTICLE;

        return <<<HTML
<h2>Combined Reels + Shorts safe area</h2>
<p>If you post the same 1080 × 1920 video to Instagram Reels and YouTube Shorts, keep important text, faces and logos out of the top 190 px, the right 170 px and the bottom 220 px. That combined safe area clears both apps' interfaces with a short caption.</p>
<table>
<thead><tr><th>Edge</th><th>Combined</th><th>Instagram Reels</th><th>YouTube Shorts</th><th>Set by</th></tr></thead>
<tbody>
<tr><td>Top</td><td>190 px</td><td>190 px</td><td>180 px</td><td>Reels</td></tr>
<tr><td>Right</td><td>170 px</td><td>160 px</td><td>170 px</td><td>Shorts</td></tr>
<tr><td>Bottom</td><td>220 px</td><td>220 px</td><td>190 px</td><td>Reels</td></tr>
</tbody>
</table>
<p>These values are derived from Softphoria's current measured Instagram Reels and YouTube Shorts interface zones (caption collapsed). They are not a separate measurement and not an official specification.</p>
{$figure}
<p><strong>About these numbers:</strong> 1080 × 1920 reference canvas · Reels measured on an iPhone 14 Plus and a Nokia 6.1 Plus, Shorts on an iPhone 14 Plus and a Samsung Galaxy A20s · last verified October 2026 · observed measurements · interfaces can vary by device, app version, account configuration and interface experiments.</p>
<p><a href="{$hub}">Check one video against Reels and Shorts at once</a> with the free Social Video Safe Zone Checker.</p>
<h2>Which platform sets each edge</h2>
<p>Each combined edge is the larger of the two apps. Instagram's top bar is the deeper one, YouTube's button column is the wider one, and Instagram's caption row reaches higher at the bottom. For per-platform detail, see the <a href="{$reels}">Instagram Reels safe zone</a> and the <a href="{$shorts}">YouTube Shorts safe zone</a>.</p>
<h2>The two caption bands</h2>
<p>Auto-captions appear in different places on the two apps, so a video posted to both has two bands to avoid:</p>
<ul>
<li><strong>Instagram Reels:</strong> y 1590 to y 1700, just above the caption area (observed on the iPhone 14 Plus).</li>
<li><strong>YouTube Shorts:</strong> y 260 to y 380, near the top.</li>
</ul>
<p>Burned-in subtitles placed in either band can overlap the app's own captions for viewers who switch them on.</p>
<h2>When the Reels caption is opened</h2>
<p>The worst case we observed is an opened Instagram caption: on the Nokia 6.1 Plus it covered up to 750 px from the bottom. It only appears when a viewer taps to read a long caption, so it is not part of the combined margins above.</p>
<h2>Other resolutions</h2>
<p>The margins scale with the frame. For a 720 × 1280 video, divide by 1.5: about 127 px top, 113 px right and 147 px bottom. For a 2160 × 3840 (4K) video, double them: 380 px top, 340 px right and 440 px bottom.</p>
<h2>What about TikTok?</h2>
<p>TikTok is not included in these combined values, because TikTok remains provisional and unmeasured in version 1 of our data. The checker shows TikTok as a clearly labelled estimate and never gives it a Pass.</p>
{$measured}
<h2>Download the combined overlay</h2>
{$download}
<p>A template shows where the zones are; the checker checks your actual frames. Wondering why other guides quote bigger margins? Read <a href="{$article}">why safe-zone numbers differ</a>.</p>
HTML;
    }

    private function articleBody(): string
    {
        $hub = self::HUB;
        $reels = '/tools/'.self::REELS;
        $shorts = '/tools/'.self::SHORTS;
        $combined = '/tools/'.self::COMBINED;

        return <<<HTML
<p>Look up the safe zone for a 1080 × 1920 Reel or Short and you will find bottom margins anywhere from about 190 px to 672 px. They are not all wrong. Most describe a different situation: ads instead of organic posts, a different caption state, another phone, or a different way of measuring. This article sorts them out, using our own measurements as examples.</p>
<p><em>By Softphoria. Our measurements were last verified in October 2026; the published figures below were checked on 8 October 2026.</em></p>
<h2>The spread, in one table</h2>
<table>
<thead><tr><th>Source type</th><th>Source</th><th>Bottom margin (1080 × 1920)</th></tr></thead>
<tbody>
<tr><td>Published platform guidance (ads)</td><td><a href="https://support.google.com/google-ads/answer/9128498?hl=en">Google Ads help: vertical video ads</a> (in-stream, in-feed and Shorts)</td><td>672 px (reference image: 288 px top, 672 px bottom, 48 px left, 192 px right)</td></tr>
<tr><td>Published platform guidance (ads)</td><td><a href="https://www.facebook.com/business/ads-guide/update/video/instagram-reels">Meta Ads Guide: Instagram Reels ads</a></td><td>35% of the height, which is 672 px (also 14% top, 6% each side)</td></tr>
<tr><td>Third-party figures</td><td>Safe-zone tools and guides for organic Reels</td><td>310 px to 480 px in the guides we reviewed on 8 October 2026, each with its own method and date</td></tr>
<tr><td>Softphoria observed</td><td><a href="{$reels}">Instagram Reels</a>, caption collapsed / opened</td><td>220 px / 750 px (opened: Nokia 6.1 Plus only)</td></tr>
<tr><td>Softphoria observed</td><td><a href="{$shorts}">YouTube Shorts</a></td><td>190 px</td></tr>
</tbody>
</table>
<p>Our values are observed measurements, not official Instagram or YouTube specifications. The platform figures are official, but for ads.</p>
<h2>Organic interface vs advertising guidance</h2>
<p>Both 672 px figures come from ad guidance. An ad carries interface an organic post does not, such as a call-to-action button, and ad guidance has to cover several placements at once. Google's vertical-video image applies to in-stream, in-feed and Shorts ads; Meta's figure is for Reels ads. That makes them deliberately cautious. They are the right numbers for ads, and a safe upper bound for anyone who wants one rule. They are larger than what we observed on ordinary posts: 220 px on Reels and 190 px on Shorts.</p>
<h2>Different interface states</h2>
<p>The same app covers different areas depending on what the viewer does. On Instagram, a collapsed caption covered the bottom 220 px; when a viewer opened a long caption, it covered up to 750 px on the Nokia 6.1 Plus. Switching auto-captions on adds another band: y 1590 to y 1700 on Reels (iPhone 14 Plus) and y 260 to y 380 on Shorts. A guide that measures one state will disagree with a guide that measures another.</p>
<h2>Caption length</h2>
<p>Longer captions take more room. In our measurements, a medium-length Instagram caption on the Nokia stretched the caption row across the full width of the frame, where a short one left the bottom-right corner to the audio tile. Guides measured with a long caption will quote larger margins than guides measured with a short one.</p>
<h2>Device differences</h2>
<p>Phones differ. Instagram's top bar measured 190 px on our iPhone 14 Plus but 90 px on the Nokia 6.1 Plus. YouTube's was 180 px on the iPhone and 160 px on the Samsung Galaxy A20s. Taller screens can also crop the sides of a 9:16 video: on the iPhone, Instagram cut about 54 px from the left edge. We publish the larger value of our test phones, so a single-phone measurement can easily come out smaller.</p>
<h2>App versions and platform updates</h2>
<p>Apps change. In our tests, the iPhone's Instagram version shrank the video when a caption was opened, while the Android version covered it. A guide measured on an older or newer app can show a different layout. That is why every number should carry a date, and why we re-check ours quarterly or sooner when a visible change is reported.</p>
<h2>Account and interface experiments</h2>
<p>Platforms test interface changes with some accounts before others, so two people on the same phone can see slightly different layouts. We have no first-hand measurement of an experiment, but it is one more reason to treat any safe-zone number as guidance and to check important posts in the app.</p>
<h2>Measurement method</h2>
<p>Numbers also differ because they are made differently:</p>
<ul>
<li><strong>Observed measurements:</strong> screenshots of real playback, measured on the frame (our method).</li>
<li><strong>Ad templates and guides:</strong> official, but for ads and often rounded to a percentage.</li>
<li><strong>"Largest possible interface" estimates:</strong> worst-case margins combining several states.</li>
<li><strong>Percentage presets:</strong> convenient starting points that scale to any size, but not measurements.</li>
</ul>
<h2>Which number to use when</h2>
<ul>
<li><strong>Running an ad:</strong> follow the platform's ad guidance.</li>
<li><strong>Posting an organic Reel or Short:</strong> use measured margins as a practical guide, such as our <a href="{$reels}">Reels</a> and <a href="{$shorts}">Shorts</a> pages.</li>
<li><strong>Posting one video to both:</strong> use the <a href="{$combined}">combined Reels + Shorts safe area</a>.</li>
<li><strong>In every case:</strong> check your actual frames rather than comparing numbers. The <a href="{$hub}">Social Video Safe Zone Checker</a> finds text and logos in your own video and shows what the interface covers. It runs in your browser; nothing is uploaded.</li>
</ul>
<h2>How we measured</h2>
<p>We made calibration videos at 1080 × 1920, played them in the official Instagram and YouTube apps and took screenshots on an iPhone 14 Plus, a Nokia 6.1 Plus (Instagram) and a Samsung Galaxy A20s (YouTube). Each interface element's edges were measured on the frame and recorded to the nearest 10 px. Last verified: October 2026. These are observations, not official specifications, and interfaces can vary by device, app version, account configuration and interface experiments. TikTok is not covered: we have not measured it, and our checker shows it only as a labelled estimate.</p>
HTML;
    }
}
