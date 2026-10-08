<?php

namespace Database\Seeders;

use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Tools\Functionalities\SocialVideoSafeZone;
use Illuminate\Database\Seeder;

/**
 * The Social Video Safe Zone Checker's landing page (Admin → Tools) as a
 * DRAFT: content, SEO and FAQ for /tools/social-video-safe-zone-checker.
 * It is never published here — review it, then publish from the admin.
 *
 * Wording rules: the zones were measured on specific phones and app
 * versions (P0, October 2026); detection is a heuristic the user checks;
 * TikTok is provisional and never gets a Pass. No accuracy guarantees.
 *
 * Never overwrites an admin's work: does nothing if a tool with this slug
 * already exists.
 */
class SocialVideoSafeZoneToolSeeder extends Seeder
{
    public const SLUG = 'social-video-safe-zone-checker';

    public function run(): void
    {
        if (Tool::query()->where('slug', self::SLUG)->exists()) {
            $this->command?->info('Social Video Safe Zone Checker already exists — left unchanged.');

            return;
        }

        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        $tool = new Tool([
            'name' => 'Social Video Safe Zone Checker',
            'slug' => self::SLUG,
            'tool_category_id' => ToolCategory::query()->where('slug', 'image-media')->value('id'),
            'functionality' => SocialVideoSafeZone::KEY,
            'icon' => 'mobile',
            'short_description' => 'Check whether the like buttons, captions and top bar of Instagram Reels and YouTube Shorts will cover the text, faces or logos in your vertical video.',
            'heading' => 'Social Video Safe Zone Checker for Reels, Shorts & TikTok',
            'introduction' => 'Upload a vertical 9:16 video or image and see what the Instagram Reels and YouTube Shorts interface covers, using zones measured on real phones. The checker flags text, faces and logos the app would hide and suggests how far to move them. TikTok is included as a clearly labelled provisional estimate. Everything runs in your browser: your file is never uploaded.',
            'how_it_works' => <<<'HTML'
<ol>
<li><strong>Upload.</strong> Choose or drop a 9:16 video (MP4, MOV, WebM) or image (PNG, JPG, WebP). The file stays on your device.</li>
<li><strong>Inspect.</strong> The checker samples frames and outlines text and logo-like areas it finds.</li>
<li><strong>Mark what matters.</strong> Check the boxes: give each one a type (text, face, logo, product), move or resize it, delete false ones and draw any it missed.</li>
<li><strong>Choose platforms.</strong> Pick Instagram Reels, YouTube Shorts and, optionally, TikTok (provisional), plus your caption length.</li>
<li><strong>Read the issues.</strong> Each platform gets a verdict and a score, with every problem listed by severity and why it matters.</li>
<li><strong>Apply the fixes.</strong> When an element is covered, the issue suggests the smallest move or shrink that clears it. Adjust the box and the result updates straight away.</li>
<li><strong>Export.</strong> Download an annotated frame, transparent overlays for your editor, a JSON report, or print a summary.</li>
</ol>
HTML,
            'use_cases' => <<<'HTML'
<ul>
<li>Checking a Reel or Short before posting, so the headline isn't hidden under the caption.</li>
<li>Placing logos and calls to action clear of the like, comment and share buttons.</li>
<li>Giving designers and editors a transparent overlay of the measured zones to work against.</li>
<li>Reviewing client or agency creatives against the same zones for both platforms at once.</li>
</ul>
HTML,
            'additional_content' => <<<'HTML'
<h2>Measured safe zones for 1080×1920 (9:16) video</h2>
<p><strong>Last verified:</strong> October 2026</p>
<p>Safe-zone measurements are based on observed platform interfaces on the test devices listed below, using the app versions current when they were measured. They are intended as practical guidance rather than official platform specifications. Platform interfaces can change between devices, app versions, account configurations and platform experiments.</p>
<p>The Instagram Reels and YouTube Shorts zones were measured in October 2026 from screenshots of calibration videos played in the official apps, on a 1080 × 1920 frame:</p>
<ul>
<li><strong>Instagram Reels</strong>, measured on an iPhone 14 Plus and a Nokia 6.1 Plus: keep the top 190 px, the right 160 px and the bottom 220 px clear (short caption).</li>
<li><strong>YouTube Shorts</strong>, measured on an iPhone 14 Plus and a Samsung Galaxy A20s: keep the top 180 px, the right 170 px and the bottom 190 px clear.</li>
<li><strong>TikTok</strong> could not be measured. Its zones are a provisional estimate, clearly labelled, and a TikTok check never shows a Pass.</li>
</ul>
<p>At a glance, the space to keep clear on a 1080 × 1920 frame:</p>
<table>
<thead><tr><th>Platform</th><th>Top</th><th>Right</th><th>Bottom</th><th>Basis</th></tr></thead>
<tbody>
<tr><td>Instagram Reels</td><td>190 px</td><td>160 px</td><td>220 px</td><td>Observed on tested devices</td></tr>
<tr><td>YouTube Shorts</td><td>180 px</td><td>170 px</td><td>190 px</td><td>Observed on tested devices</td></tr>
<tr><td>Combined Reels + Shorts</td><td>190 px</td><td>170 px</td><td>220 px</td><td>Union of measured Reels + Shorts zones</td></tr>
<tr><td>TikTok</td><td>Provisional</td><td>—</td><td>—</td><td>Not measured; estimate only</td></tr>
</tbody>
</table>
<p>The measurements combine observations from iOS and Android test devices. Different devices or future app updates may display interface elements differently.</p>
<p>Longer captions, auto-captions and taller phone screens cover more of the frame; choose those settings in the checker to include them.</p>
<h3>About the TikTok estimate</h3>
<p>TikTok was not directly measured in the test environment, so its checker preset is provisional. It uses the union of the measured Instagram Reels and YouTube Shorts zones as a conservative working estimate. It is not treated as an official TikTok specification, and a TikTok check never shows a Pass.</p>
<h2>Safe-zone guides and PNG overlays</h2>
<ul>
<li><a href="/tools/instagram-reels-safe-zone">Instagram Reels safe zone</a>: measured margins, plus the caption-open and auto-caption areas</li>
<li><a href="/tools/youtube-shorts-safe-zone">YouTube Shorts safe zone</a>: measured margins and the auto-caption band near the top</li>
<li><a href="/tools/1080x1920-safe-zone-guide">1080×1920 safe zone guide for Reels &amp; Shorts</a>: one set of margins for both apps</li>
<li><a href="/blog/why-social-media-safe-zone-numbers-differ">Why safe-zone numbers differ</a>: organic posts vs ad guidance, devices and interface states</li>
</ul>
<p>Transparent 1080 × 1920 overlays of the measured zones, to place over your video in an editor:</p>
<ul>
<li><a href="/downloads/social-video-safe-zone/softphoria-instagram-reels-safe-zone-1080x1920.png">Download the Instagram Reels safe-zone overlay (PNG)</a></li>
<li><a href="/downloads/social-video-safe-zone/softphoria-youtube-shorts-safe-zone-1080x1920.png">Download the YouTube Shorts safe-zone overlay (PNG)</a></li>
<li><a href="/downloads/social-video-safe-zone/softphoria-reels-shorts-combined-safe-zone-1080x1920.png">Download the combined Reels + Shorts safe-zone overlay (PNG)</a></li>
</ul>
<p>Measured and maintained by Softphoria. <a href="/about">About Softphoria</a></p>
HTML,
            'important_notes' => <<<'HTML'
<p>The zones were measured on specific phones and app versions. Apps change their layouts, so treat the results as careful guidance rather than a guarantee, and check important posts in the app itself.</p>
<p>Automatic detection can miss or misplace elements. Review the boxes before relying on a verdict.</p>
HTML,
        ]);
        $tool->status = ToolStatus::Draft;
        $tool->created_by = $actor?->getKey();
        $tool->updated_by = $actor?->getKey();
        $tool->save();

        $tool->seo()->create([
            'meta_title' => 'Social Video Safe Zone Checker — Reels, Shorts & TikTok',
            'meta_description' => 'See what Instagram Reels and YouTube Shorts cover in your 9:16 video or image, with fixes and PNG overlays. TikTok is an estimate. Files are never uploaded.',
        ]);

        $faqs = [
            ['Is my video uploaded anywhere?', 'No. The checker runs entirely in your browser tab. Your video or image is read on your device and never sent to a server.'],
            ['Which platforms does it check?', 'Instagram Reels and YouTube Shorts, using zones measured in the official apps. TikTok is included as a provisional estimate because it could not be measured; a TikTok check never shows a Pass.'],
            ['What sizes and formats work?', 'Vertical 9:16 videos (MP4, MOV, WebM) and images (PNG, JPG, WebP). Other shapes such as 4:5 or 1:1 are shown but not scored, because each app displays them differently. Up to the first 3 minutes of a video are analysed.'],
            ['Why does it say "Needs review"?', 'Something sits partly inside an area the app covers or may cover. Look at the listed issue and decide whether that element matters; the suggested fix shows how far to move it.'],
            ['How accurate is it?', 'The zones were measured on specific phones and app versions, and apps change over time. Automatic detection can miss elements, so check the boxes and adjust them. Use the result as a careful guide, not a guarantee.'],
        ];

        foreach ($faqs as $order => [$question, $answer]) {
            $tool->faqs()->create(['question' => $question, 'answer' => $answer, 'is_visible' => true, 'sort_order' => $order]);
        }

        $this->command?->info('Social Video Safe Zone Checker created as a draft. Review and publish it in Admin → Tools.');
    }
}
