<?php

namespace Database\Seeders;

use App\Actions\Page\CreatePageAction;
use App\Enums\PageSectionType;
use App\Enums\PageTemplate;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The Image Requirements Checker's SEO content cluster (stage 1): eight
 * image-size guides as tool-guide CMS Pages under /tools/{slug}, all DRAFTS
 * for review. Each answers the question first, then links to the checker.
 *
 * Values match resources/js/tools/image-requirements/data/profiles.js
 * (reviewed 8 October 2026) and are labelled the same way: platform limit,
 * official recommendation, third-party recommendation or practical guidance.
 * A value not confirmed on an official page is never called a requirement.
 * FAQs are visible content only (no FAQPage markup on guides).
 *
 * Author: Softphoria (organisation). Never overwrites an existing slug.
 */
class ImageSizeGuidesSeeder extends Seeder
{
    public const CHECKER = '/tools/image-requirements-checker';

    public const GUIDES = [
        'hub' => 'social-media-image-sizes',
        'instagram' => 'instagram-image-sizes',
        'youtube' => 'youtube-thumbnail-size',
        'facebook' => 'facebook-image-sizes',
        'linkedin' => 'linkedin-image-sizes',
        'x' => 'x-twitter-image-sizes',
        'pinterest' => 'pinterest-image-sizes',
        'og' => 'open-graph-image-size',
    ];

    private const REVIEWED = '8 October 2026';

    public function run(): void
    {
        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        if (! $actor) {
            return;
        }

        foreach ($this->pages() as $page) {
            if (Page::withTrashed()->where('slug', $page['slug'])->exists()) {
                $this->command?->info("/tools/{$page['slug']} already exists — left unchanged.");

                continue;
            }

            $created = app(CreatePageAction::class)->handle($page, $actor);
            $created->forceFill(['author_id' => null])->save();
            $this->command?->info("Created /tools/{$page['slug']} as a draft.");
        }
    }

    // --- Shared building blocks --------------------------------------------

    /** Every {placeholder} in guide HTML: the checker, guides and safe-zone pages. */
    private function links(string $html): string
    {
        $map = ['{checker}' => self::CHECKER, '{svsz}' => '/tools/social-video-safe-zone-checker',
            '{reels-safe}' => '/tools/instagram-reels-safe-zone', '{shorts-safe}' => '/tools/youtube-shorts-safe-zone'];
        foreach (self::GUIDES as $key => $slug) {
            $map["{{$key}}"] = "/tools/{$slug}";
        }

        return strtr($html, $map);
    }

    /**
     * @param  array<int, string>  $head
     * @param  array<int, array<int, string>>  $rows
     */
    private function table(array $head, array $rows): string
    {
        $th = implode('', array_map(fn (string $h): string => "<th>{$h}</th>", $head));
        $tr = implode("\n", array_map(fn (array $r): string => '<tr>'.implode('', array_map(fn (string $c): string => "<td>{$c}</td>", $r)).'</tr>', $rows));

        return "<table>\n<thead><tr>{$th}</tr></thead>\n<tbody>\n{$tr}\n</tbody>\n</table>";
    }

    private function checkCta(string $what): string
    {
        return "<p><strong><a href=\"{checker}\">Check your image</a></strong> — {$what} The free Image Requirements Checker reads your actual file, shows which placements it fits, previews every crop and explains what to change. It runs in your browser; your image is never uploaded.</p>";
    }

    /** @param  array<int, array{0: string, 1: string}>  $faqs */
    private function faq(array $faqs): string
    {
        $items = implode("\n", array_map(fn (array $f): string => "<h3>{$f[0]}</h3>\n<p>{$f[1]}</p>", $faqs));

        return "<h2>Frequently asked questions</h2>\n{$items}";
    }

    /** @param  array<int, array{0: string, 1: ?string}>  $sources  [label, url|null] */
    private function sources(array $sources): string
    {
        $items = implode("\n", array_map(fn (array $s): string => '<li>'.($s[1] ? "<a href=\"{$s[1]}\">{$s[0]}</a>" : $s[0]).'</li>', $sources));
        $date = self::REVIEWED;

        return <<<HTML
<h2>Sources and how to read these values</h2>
<p><strong>Last reviewed:</strong> {$date}, the same review as the Image Requirements Checker.</p>
<p>Each value above is labelled: a <strong>platform limit</strong> is documented by the platform and uploads outside it fail; an <strong>official recommendation</strong> comes from the platform's own help pages; a <strong>third-party recommendation</strong> is widely published but not confirmed on an official page; <strong>practical guidance</strong> is our advice. Platforms change their layouts without notice, so check important images in the app itself.</p>
<ul>
{$items}
</ul>
HTML;
    }

    /** @param  array<int, string>  $keys  guide keys from GUIDES */
    private function related(array $keys, string $extra = ''): string
    {
        $titles = [
            'hub' => 'Social media image sizes (all platforms)', 'instagram' => 'Instagram image sizes', 'youtube' => 'YouTube thumbnail size',
            'facebook' => 'Facebook image sizes', 'linkedin' => 'LinkedIn image sizes', 'x' => 'X (Twitter) image sizes',
            'pinterest' => 'Pinterest image sizes', 'og' => 'Open Graph image size',
        ];
        $items = implode("\n", array_map(fn (string $k): string => "<li><a href=\"{{$k}}\">{$titles[$k]}</a></li>", $keys));

        return "<h2>Related guides</h2>\n<ul>\n{$items}\n{$extra}</ul>";
    }

    /**
     * @param  array<string, string>  $seo
     * @return array<string, mixed>
     */
    private function page(string $key, string $title, string $summary, string $body, array $seo, string $ctaHeading): array
    {
        return [
            'title' => $title,
            'slug' => self::GUIDES[$key],
            'template' => PageTemplate::Standard->value,
            'is_tool_guide' => true,
            'summary' => $summary,
            'sections' => [
                ['section_type' => PageSectionType::RichText->value, 'title' => null, 'is_enabled' => true, 'content_json' => ['body' => $this->links($body)]],
                ['section_type' => PageSectionType::Cta->value, 'title' => 'Check your image', 'is_enabled' => true, 'content_json' => [
                    'eyebrow' => 'Free tool',
                    'heading' => $ctaHeading,
                    'description' => 'Upload any JPG, PNG or WebP image. The Image Requirements Checker shows where it fits, previews each crop and safe zone, and explains what to change. Your image never leaves your browser.',
                    'cta_label' => 'Open the Image Requirements Checker',
                    'cta_url' => self::CHECKER,
                ]],
            ],
            'seo' => $seo,
        ];
    }

    // --- Pages -------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function pages(): array
    {
        return [
            $this->hub(), $this->instagram(), $this->youtube(), $this->facebook(),
            $this->linkedin(), $this->x(), $this->pinterest(), $this->openGraph(),
        ];
    }

    private function hub(): array
    {
        $table = $this->table(['Platform', 'Placement', 'Recommended size', 'Shape', 'Status'], [
            ['Instagram', 'Feed post (portrait)', '1080 × 1350 px', '4:5', 'Official recommendation'],
            ['Instagram', 'Story / Reel cover', '1080 × 1920 px', '9:16', 'Third-party recommendation'],
            ['Facebook', 'Feed post', '1080 × 1350 px', '4:5', 'Official recommendation (ads)'],
            ['Facebook', 'Cover photo', '851 × 315 px (min 400 × 150)', 'about 2.7:1', 'Official (minimum is a limit)'],
            ['LinkedIn', 'Post / link preview', '1200 × 627 px', '1.91:1', 'Official recommendation'],
            ['LinkedIn', 'Company Page cover', '1512 × 256 px, max 3 MB', 'about 5.9:1', 'Platform limit'],
            ['X (Twitter)', 'Header', '1500 × 500 px', '3:1', 'Official recommendation'],
            ['X (Twitter)', 'Profile photo', '400 × 400 px, max 2 MB', '1:1', 'Official'],
            ['YouTube', 'Video thumbnail', '1280 × 720 px (min 640 px wide)', '16:9', 'Minimum is a limit'],
            ['YouTube', 'Channel banner', '2560 × 1440 px (min 2048 × 1152), max 6 MB', '16:9', 'Platform limit'],
            ['Pinterest', 'Standard Pin', '1000 × 1500 px', '2:3', 'Third-party recommendation'],
            ['Website', 'Open Graph image', '1200 × 630 px, max 8 MB (Facebook)', '1.91:1', 'Practical default'],
        ]);

        $body = <<<HTML
<p><strong>Quick answer:</strong> make feed images 1080 px wide (1080 × 1350 for portrait posts), vertical Stories and Reel covers 1080 × 1920, YouTube thumbnails 1280 × 720, and link-preview (Open Graph) images 1200 × 630. Covers and banners differ on every platform, and a few — YouTube's channel banner and LinkedIn's Company Page cover — have hard minimum sizes or file-size limits.</p>
{$this->checkCta('not sure your image fits?')}
<h2>Quick reference: the sizes most people need</h2>
{$table}
<p>Not every number on the internet is a rule. In this guide, a <strong>platform limit</strong> means the upload fails or the platform refuses it; everything else is a recommendation that helps the image look its best.</p>
<h2>Instagram</h2>
<p>Instagram keeps photos 320–1080 px wide at their original resolution and resizes wider ones down to 1080 px. Feed photos can be any shape from landscape 1.91:1 to portrait 4:5; anything outside that range is cropped. Portrait 4:5 (1080 × 1350) uses the most screen space in the feed. Stories and Reels are full-screen 9:16. <a href="{instagram}">Instagram image sizes in detail →</a></p>
<h2>Facebook</h2>
<p>For feed posts, Meta recommends 4:5 for image ads, and a 1080 px-wide image is a safe choice for organic posts. Cover photos must be at least 400 × 150 px; Facebook recommends 851 × 315 px and says covers display at 16:9 on computers and 2.4:1 on phones, so keep the important part in the middle. <a href="{facebook}">Facebook image sizes in detail →</a></p>
<h2>LinkedIn</h2>
<p>LinkedIn recommends 1.91:1 (1200 × 627 px) for post and link-preview images, with a 5 MB limit for shared link images. Company Page images must be PNG or JPEG under 3 MB, and the Page cover is 1512 × 256 px. <a href="{linkedin}">LinkedIn image sizes in detail →</a></p>
<h2>X (Twitter)</h2>
<p>X recommends a 1500 × 500 px header and a 400 × 400 px profile photo (JPEG, GIF or PNG, up to 2 MB), and warns that about 60 px at the top and bottom of the header can be cropped. For post images, 16:9 (1200 × 675) is widely used. <a href="{x}">X (Twitter) image sizes in detail →</a></p>
<h2>YouTube</h2>
<p>Thumbnails should be 16:9 and at least 640 px wide; 1280 × 720 is the practical standard, and YouTube now lists up to 3840 × 2160. The file can be up to 50 MB from a computer but only 2 MB from the mobile app. Channel banners must be at least 2048 × 1152 px and under 6 MB, with text kept inside the central 1235 × 338 px. <a href="{youtube}">YouTube thumbnail and banner sizes in detail →</a></p>
<h2>Pinterest</h2>
<p>Pins work best at 2:3, usually 1000 × 1500 px. Pinterest doesn't publish its organic Pin limits in one official place, so treat the widely quoted 20 MB maximum as a guide rather than a guarantee. <a href="{pinterest}">Pinterest image sizes in detail →</a></p>
<h2>Website link previews (Open Graph)</h2>
<p>When someone shares your page, apps read its <code>og:image</code>. 1200 × 630 px (1.91:1) is the practical default that works on Facebook, LinkedIn and most messaging apps — but it is not a rule of the Open Graph protocol itself. <a href="{og}">Open Graph image size explained →</a></p>
<h2>Shape, cropping and safe areas</h2>
<p>Shape (aspect ratio) matters more than pixel size. An image with the wrong shape is cropped — usually from the middle outwards — so faces, logos and text near the edges are the first to disappear. Vertical formats add another problem: buttons, captions and the top bar cover parts of the frame. For Reels and Shorts, see our measured <a href="{reels-safe}">Instagram Reels safe zone</a> and <a href="{shorts-safe}">YouTube Shorts safe zone</a>, or check a video with the <a href="{svsz}">Social Video Safe Zone Checker</a>.</p>
<h2>Formats and file size</h2>
<p>JPG suits photos; PNG suits graphics, screenshots and anything with transparency. WebP is great on websites but not every platform accepts WebP uploads, so JPG or PNG is the safest choice for social media. Many platforms convert uploads to JPG, which removes transparency. Keep files reasonably small: some placements allow only 2–3 MB.</p>
<h2>Common mistakes</h2>
<ul>
<li>Designing everything as a square and letting each platform crop it differently.</li>
<li>Putting text or logos near the edges of covers and banners, where phones crop first.</li>
<li>Uploading a tiny image: platforms enlarge it and it looks soft.</li>
<li>Exporting huge files that exceed a 2–6 MB limit.</li>
<li>Uploading a transparent PNG and getting a black or white background.</li>
<li>Leaving GPS location in a photo's metadata before publishing it on your own website.</li>
</ul>
<h2>Practical recommendations</h2>
<ul>
<li>Export at the recommended size, not just the minimum.</li>
<li>Keep the subject and any text in the middle 80% of the frame.</li>
<li>Use JPG for photos and PNG for graphics; avoid WebP for social uploads.</li>
<li>Check the image before publishing, ideally against every placement at once.</li>
</ul>
{$this->checkCta('see every placement your image fits in one go.')}
{$this->faq([
            ['What is the best image size for social media?', 'There is no single size. For most feeds, a 1080 px-wide image works well: 1080 × 1350 for portrait posts or 1080 × 1080 for squares. Stories and Reels use 1080 × 1920, YouTube thumbnails 1280 × 720 and link previews 1200 × 630.'],
            ['Which social media image sizes are actual requirements?', 'Only a few are hard limits, such as YouTube\'s 2048 × 1152 px minimum and 6 MB limit for channel banners, its 640 px minimum width for thumbnails, Facebook\'s 400 × 150 px minimum for covers and LinkedIn\'s 3 MB limit for Page images. Most other sizes are recommendations.'],
            ['What happens if my image has the wrong shape?', 'The platform crops it, usually keeping the middle, or shows it with bars. Faces and text near the edges are the most likely to be cut off.'],
            ['Should I use PNG or JPG for social media?', 'Use JPG for photos and PNG for graphics, screenshots and logos. Both are accepted everywhere covered here; WebP is not always accepted for social uploads.'],
            ['How often do social media image sizes change?', 'Platforms change layouts several times a year. We review these values regularly; this guide and the Image Requirements Checker were last reviewed on 8 October 2026.'],
        ])}
{$this->sources([
            ['Instagram Help Center: photo resolution', 'https://help.instagram.com/1631821640426723'],
            ['Meta Ads Guide: Facebook Feed and Instagram Stories image ads', 'https://www.facebook.com/business/ads-guide/update/image/facebook-feed'],
            ['Facebook Help Center: cover photo sizes', 'https://www.facebook.com/help/125379114252045'],
            ['LinkedIn Help: image specifications for Pages', 'https://www.linkedin.com/help/linkedin/answer/70781'],
            ['LinkedIn Help: make your website shareable', 'https://www.linkedin.com/help/linkedin/answer/46687'],
            ['X Help Center: profile photo and header', 'https://help.x.com/en/managing-your-account/common-issues-when-uploading-profile-photo'],
            ['YouTube Help: video thumbnails', 'https://support.google.com/youtube/answer/72431'],
            ['YouTube Help: channel branding', 'https://support.google.com/youtube/answer/10456525'],
            ['Meta for Developers: images in link shares', 'https://developers.facebook.com/docs/sharing/webmasters/images/'],
            ['Pinterest sizes: widely published guides (no single official page found)', null],
        ])}
{$this->related(['instagram', 'youtube', 'facebook', 'linkedin', 'x', 'pinterest', 'og'])}
HTML;

        return $this->page('hub', 'Social Media Image Sizes & Requirements for 2026',
            'One reference for the image sizes that matter on Instagram, Facebook, LinkedIn, X, YouTube and Pinterest — and which values are real platform limits.',
            $body, [
                'meta_title' => 'Social Media Image Sizes & Requirements 2026 | Softphoria',
                'meta_description' => 'Every key image size for Instagram, Facebook, LinkedIn, X, YouTube and Pinterest in one table, showing which values are limits and which are recommendations.',
            ], 'Check your image against every platform at once');
    }

    private function instagram(): array
    {
        $table = $this->table(['Placement', 'Recommended size', 'Shape', 'Status'], [
            ['Feed post — portrait', '1080 × 1350 px', '4:5', 'Official range; size is a recommendation'],
            ['Feed post — square', '1080 × 1080 px', '1:1', 'Official range; size is a recommendation'],
            ['Feed post — landscape', '1080 × 566 px', '1.91:1', 'Official range; size is a recommendation'],
            ['Story', '1080 × 1920 px', '9:16', 'Third-party recommendation (Meta lists 1440 × 2560 for Story ads)'],
            ['Reel cover', '1080 × 1920 px', '9:16 (grid preview 3:4)', 'Third-party recommendation'],
            ['Profile photo', 'At least 320 × 320 px, square', '1:1 (shown in a circle)', 'Practical guidance'],
        ]);

        $body = <<<HTML
<p><strong>Quick answer:</strong> post portrait photos at 1080 × 1350 px (4:5) — the tallest shape Instagram's help page lists for the feed. Use 1080 × 1080 for squares, 1080 × 566 for landscape, and 1080 × 1920 (9:16) for Stories and Reel covers.</p>
{$this->checkCta('see how Instagram will crop your photo before you post.')}
<h2>Instagram image sizes at a glance</h2>
{$table}
<h2>Feed posts: portrait, square and landscape</h2>
<p>Instagram's help center says photos between 320 and 1080 px wide keep their original resolution; wider photos are resized to 1080 px wide and narrower ones are enlarged. The shape must be between landscape 1.91:1 and portrait 4:5 — anything outside that range is cropped to fit.</p>
<ul>
<li><strong>Portrait 4:5, 1080 × 1350 px:</strong> takes the most space in the feed, which is why most creators use it.</li>
<li><strong>Square 1:1, 1080 × 1080 px:</strong> the classic format; safe when you reuse the image elsewhere.</li>
<li><strong>Landscape 1.91:1, 1080 × 566 px:</strong> the widest shape Instagram shows without cropping; it appears small on phones.</li>
</ul>
<h2>4:5 or 3:4?</h2>
<p>In 2025 Instagram announced support for taller 3:4 photos (1080 × 1440 px), the shape most phone cameras use. Instagram's help page, however, still lists 4:5 as the tallest feed shape, and tools that post through Meta's publishing API may still crop 3:4 photos to 4:5. Until the documentation catches up, <strong>4:5 is the safe choice</strong>. If you post 3:4, keep anything important away from the top and bottom edges.</p>
<h2>Stories</h2>
<p>Stories fill the screen at 9:16, so export at 1080 × 1920 px (Meta recommends 1440 × 2560 for Story ads, with a minimum width of 500 px and JPG or PNG files up to 30 MB). The profile row at the top and the reply bar at the bottom cover parts of the frame: Meta advises keeping about 14% of the top, 35% of the bottom and 6% of each side of a Story ad free of text. Organic Stories show less interface, so treat that as a cautious guide.</p>
<h2>Reel covers</h2>
<p>A Reel cover is a full 9:16 image (1080 × 1920 px), but your profile grid shows a 3:4 crop of its middle — put the title and faces in the centre. While the Reel plays, buttons and the caption cover the edges; our <a href="{reels-safe}">Instagram Reels safe zone guide</a> shows exactly where, measured on real phones, and the <a href="{svsz}">Social Video Safe Zone Checker</a> checks your video.</p>
<h2>Profile photo</h2>
<p>Instagram shows profile photos as a small circle and doesn't publish an official size. A square image of at least 320 × 320 px, with the face or logo centred, displays sharply everywhere; the corners are always hidden.</p>
<h2>Formats and file size</h2>
<p>Upload JPG for photos and PNG for graphics. Instagram converts images to JPG, so transparent areas in a PNG become a solid colour. Instagram doesn't publish a file-size limit for organic posts; very large files are simply compressed.</p>
<h2>Cropping: what gets lost</h2>
<p>If your photo is wider than 1.91:1 or taller than 4:5, Instagram crops it to the nearest allowed shape, keeping the middle. A 16:9 photo posted as a portrait loses about 55% of its width — usually the subject's surroundings and any text near the sides.</p>
<h2>Common mistakes</h2>
<ul>
<li>Posting 3:4 photos and finding the top and bottom cropped when scheduled through a third-party tool.</li>
<li>Placing captions on Stories or Reel covers where the interface or the 3:4 grid crop hides them.</li>
<li>Uploading images narrower than 1080 px, which look soft when enlarged.</li>
<li>Using a transparent PNG and getting a black background.</li>
</ul>
<h2>Practical recommendations</h2>
<ul>
<li>Default to 1080 × 1350 for feed posts and 1080 × 1920 for Stories and Reels.</li>
<li>Keep text and faces in the middle of vertical images.</li>
<li>Check a 3:4 photo before posting rather than assuming it won't be cropped.</li>
</ul>
{$this->faq([
            ['What size should an Instagram post be?', '1080 px wide. For the biggest presence in the feed, use portrait 1080 × 1350 px (4:5); squares are 1080 × 1080 px and landscape posts 1080 × 566 px.'],
            ['What is the best Instagram portrait size?', '1080 × 1350 px, a 4:5 shape. It is the tallest shape listed in Instagram\'s help center for feed photos.'],
            ['What size should an Instagram Story be?', '1080 × 1920 px (9:16). Keep text away from the top and bottom, where the profile row and reply bar sit.'],
            ['Does Instagram crop 3:4 images?', 'It can. Instagram announced 3:4 support in 2025, but its help page still lists 4:5 as the tallest feed shape and some scheduling tools crop 3:4 to 4:5. Check the image before posting, or use 4:5 to be safe.'],
            ['Why does my Instagram photo look blurry?', 'It was probably narrower than 1080 px, so Instagram enlarged it, or it was heavily compressed. Export at 1080 px wide or larger.'],
        ])}
{$this->sources([
            ['Instagram Help Center: photo resolution (1.91:1 to 4:5, 320–1080 px)', 'https://help.instagram.com/1631821640426723'],
            ['Meta Ads Guide: Instagram Stories image ads', 'https://www.facebook.com/business/ads-guide/update/image/instagram-story'],
            ['3:4 support: announced by Instagram in 2025 (not yet in the help center)', null],
            ['Reel cover grid preview and profile photo: widely published guidance', null],
        ])}
{$this->related(['hub', 'facebook', 'pinterest', 'og'], '<li><a href="{reels-safe}">Instagram Reels safe zone (measured)</a></li>'."\n")}
HTML;

        return $this->page('instagram', 'Instagram Image Sizes & Requirements for 2026',
            'Feed posts, Stories, Reel covers and profile photos: the sizes and shapes Instagram uses, what the 3:4 change means, and where Instagram crops.',
            $body, [
                'meta_title' => 'Instagram Image Sizes & Dimensions 2026 | Softphoria',
                'meta_description' => 'Feed posts at 1080 × 1350, squares, landscape, Stories, Reel covers and profile photos — plus what really happens to 3:4 photos and where Instagram crops.',
            ], 'Check your photo before you post it on Instagram');
    }

    private function youtube(): array
    {
        $table = $this->table(['Image', 'Size', 'Status'], [
            ['Thumbnail — recommended', '1280 × 720 px (16:9)', 'Practical standard; YouTube lists up to 3840 × 2160'],
            ['Thumbnail — minimum width', '640 px', 'Platform limit'],
            ['Thumbnail — file size', 'Up to 50 MB from a computer, 2 MB from the mobile app', 'Platform limits'],
            ['Thumbnail — format', 'JPG or PNG', 'Official'],
            ['Channel banner — recommended', '2560 × 1440 px (16:9)', 'Official recommendation'],
            ['Channel banner — minimum', '2048 × 1152 px', 'Platform limit'],
            ['Channel banner — safe area', '1235 × 338 px in the centre', 'Official'],
            ['Channel banner — file size', 'Up to 6 MB', 'Platform limit'],
            ['Profile picture', 'Square; shown at 98 × 98 px; up to 15 MB (JPG, GIF, BMP or PNG, not animated)', 'Official'],
        ]);

        $body = <<<HTML
<p><strong>Quick answer:</strong> make YouTube thumbnails 1280 × 720 px (16:9), at least 640 px wide. Keep the file under 2 MB if you might upload from the mobile app (the computer limit is 50 MB). Channel banners must be at least 2048 × 1152 px and under 6 MB, with text inside the central 1235 × 338 px.</p>
{$this->checkCta('make sure your thumbnail or banner meets YouTube\'s limits.')}
<h2>YouTube image sizes at a glance</h2>
{$table}
<h2>Thumbnails</h2>
<p>YouTube's help center asks for a 16:9 thumbnail at least 640 px wide, in JPG or PNG. It now lists 3840 × 2160 px as the resolution to aim for, but 1280 × 720 px remains the practical standard: it is sharp on every screen and far easier to keep under the mobile file limit.</p>
<h3>Recommended, minimum and file size: the difference</h3>
<ul>
<li><strong>Recommended (1280 × 720 px):</strong> what you should export. Larger, up to 3840 × 2160, is also fine.</li>
<li><strong>Minimum (640 px wide):</strong> a hard limit — narrower images are rejected.</li>
<li><strong>File size:</strong> up to 50 MB when you upload from a computer, but only 2 MB from the YouTube mobile app. A 1280 × 720 JPG is normally well under 2 MB.</li>
</ul>
<h3>Common thumbnail mistakes</h3>
<ul>
<li>Using a shape other than 16:9, which leaves black bars or gets cropped.</li>
<li>Putting text in the bottom-right corner, where YouTube shows the video length.</li>
<li>Text too small to read at phone size — check it at a few hundred pixels wide.</li>
<li>Exporting a huge PNG that is over the 2 MB mobile limit.</li>
</ul>
<h2>Channel banner</h2>
<p>The banner is the most cropped image on YouTube. Upload at least 2048 × 1152 px (16:9) — YouTube recommends 2560 × 1440 px for TVs — and keep the file under 6 MB.</p>
<p><strong>Safe area:</strong> TVs show the whole banner, computers show a wide strip across the middle, and phones show even less. Only the central <strong>1235 × 338 px</strong> is guaranteed to appear on every device, so put your name, logo and any text there and treat the rest as background.</p>
<h2>Profile picture</h2>
<p>YouTube shows your profile picture as a circle at 98 × 98 px and accepts JPG, GIF (not animated), BMP or PNG up to 15 MB. Upload a square image well above that size, with the face or logo centred.</p>
<h2>YouTube Shorts</h2>
<p>Shorts use the full 9:16 frame, and the buttons, title and auto-captions cover parts of it. Our <a href="{shorts-safe}">YouTube Shorts safe zone guide</a> shows the measured areas to keep clear, and the <a href="{svsz}">Social Video Safe Zone Checker</a> checks your video against them.</p>
<h2>Practical recommendations</h2>
<ul>
<li>Thumbnails: 1280 × 720 JPG, large readable text, nothing important bottom-right.</li>
<li>Banners: design at 2560 × 1440 with all text inside the central 1235 × 338.</li>
<li>Check the file size if you upload from your phone.</li>
</ul>
{$this->faq([
            ['What size should a YouTube thumbnail be?', '1280 × 720 px is the practical standard. YouTube requires at least 640 px wide and lists up to 3840 × 2160 px.'],
            ['What is the YouTube thumbnail aspect ratio?', '16:9. Other shapes may be shown with black bars or cropped.'],
            ['How large can a YouTube thumbnail file be?', 'Up to 50 MB when you upload from a computer and 2 MB from the YouTube mobile app, according to YouTube\'s help center.'],
            ['What is the YouTube banner safe area?', 'The central 1235 × 338 px of a 2560 × 1440 px banner. It is the only part guaranteed to show on every device.'],
            ['Why is my YouTube banner cropped on my phone?', 'Phones and computers show only a strip across the middle of the banner. Keep text and logos inside the 1235 × 338 px safe area.'],
        ])}
{$this->sources([
            ['YouTube Help: add video thumbnails (minimum width, formats, 50 MB / 2 MB)', 'https://support.google.com/youtube/answer/72431'],
            ['YouTube Help: manage your channel branding (banner, safe area, profile picture)', 'https://support.google.com/youtube/answer/10456525'],
        ])}
{$this->related(['hub', 'og', 'instagram'], '<li><a href="{shorts-safe}">YouTube Shorts safe zone (measured)</a></li>'."\n")}
HTML;

        return $this->page('youtube', 'YouTube Thumbnail Size & Image Requirements for 2026',
            'Thumbnail, channel banner and profile picture sizes for YouTube, with the difference between recommended sizes, hard minimums and the banner safe area.',
            $body, [
                'meta_title' => 'YouTube Thumbnail Size & Requirements 2026 | Softphoria',
                'meta_description' => '1280 × 720 thumbnails, the 640 px minimum and 2 MB vs 50 MB limits, plus channel banner sizes and the 1235 × 338 safe area, from YouTube\'s help pages.',
            ], 'Check your thumbnail or banner against YouTube\'s limits');
    }

    private function facebook(): array
    {
        $table = $this->table(['Placement', 'Size', 'Status'], [
            ['Feed post', '1080 × 1350 px (4:5); Meta lists 1440 × 1800 for image ads, minimum 600 px wide', 'Official recommendation (ads)'],
            ['Cover photo — minimum', '400 × 150 px', 'Platform limit'],
            ['Cover photo — recommended', '851 × 315 px, sRGB JPG under 100 KB for fastest loading', 'Official recommendation'],
            ['Cover photo — display', '16:9 on computers, 2.4:1 on phones (as Facebook describes it)', 'Official description'],
            ['Profile picture', 'At least 320 × 320 px, square (shown in a circle)', 'Third-party recommendation'],
            ['Shared link image', '1200 × 630 px (1.91:1), minimum 200 × 200, up to 8 MB', 'Official (Meta for Developers)'],
        ]);

        $body = <<<HTML
<p><strong>Quick answer:</strong> use 1080 × 1350 px (4:5) for feed posts, 851 × 315 px for cover photos (at least 400 × 150), a square of at least 320 × 320 px for your profile picture, and 1200 × 630 px for images that appear when your website is shared.</p>
{$this->checkCta('see how Facebook will crop your cover or post.')}
<h2>Facebook image sizes at a glance</h2>
{$table}
<h2>Feed posts</h2>
<p>For image ads, Meta recommends a 4:5 shape at 1440 × 1800 px, at least 600 px wide, as a JPG or PNG up to 30 MB. For ordinary posts, a 1080 px-wide image at 4:5 (1080 × 1350) or 1:1 (1080 × 1080) is a safe, sharp choice. Very wide or very tall images may be cropped in the feed preview.</p>
<h2>Cover photo: why the numbers seem to disagree</h2>
<p>Facebook's own help page gives several figures, and they describe different things:</p>
<ul>
<li><strong>Minimum:</strong> 400 px wide and 150 px tall — smaller images can't be used.</li>
<li><strong>Recommended file:</strong> 851 × 315 px, as an sRGB JPG under 100 KB, for the fastest loading.</li>
<li><strong>How it's displayed:</strong> Facebook says covers show at a 16:9 ratio on computers and 2.4:1 on smartphones, and that the profile picture covers about 40 px of the cover on mobile.</li>
</ul>
<p>These display ratios are wider or taller than the 851 × 315 recommendation, so Facebook shows different parts of the same image on different screens. There isn't one shape that avoids every crop: keep your name, text and faces in the middle, and expect the edges to be trimmed somewhere.</p>
<h2>Profile picture</h2>
<p>Profile pictures are shown as a circle. Facebook doesn't publish a single official upload size we could confirm; a square image of at least 320 × 320 px with the subject centred is widely recommended and displays sharply.</p>
<h2>Images for shared links</h2>
<p>When someone shares a page from your website, Facebook uses its Open Graph image. Meta recommends at least 1200 × 630 px, about 1.91:1, with an absolute minimum of 200 × 200 px and a file under 8 MB. See the <a href="{og}">Open Graph image size guide</a>.</p>
<h2>Formats</h2>
<p>JPG for photos, PNG for graphics. Facebook converts uploads, so transparency in a PNG may become a solid colour.</p>
<h2>Common mistakes</h2>
<ul>
<li>Designing a cover at exactly 851 × 315 with text at the edges — phones crop it.</li>
<li>Logos in profile pictures touching the corners, which the circle hides.</li>
<li>Low-resolution covers that Facebook enlarges.</li>
</ul>
<h2>Practical recommendations</h2>
<ul>
<li>Design covers with a generous central "safe" area and plain edges.</li>
<li>Use 4:5 for feed images you want to stand out on phones.</li>
<li>Preview your cover crop for both computer and phone before uploading.</li>
</ul>
{$this->faq([
            ['What size is a Facebook cover photo?', 'Facebook recommends 851 × 315 px and requires at least 400 × 150 px. It displays covers at 16:9 on computers and 2.4:1 on phones, so keep important content in the middle.'],
            ['What is the best image size for a Facebook post?', 'A 1080 px-wide image works well: 1080 × 1350 (4:5) or 1080 × 1080 (1:1). Meta recommends 4:5 for image ads.'],
            ['What size is a Facebook profile picture?', 'Use a square of at least 320 × 320 px. It is shown in a circle, so keep the face or logo away from the corners.'],
            ['What image size does Facebook use for shared links?', 'At least 1200 × 630 px at about 1.91:1, under 8 MB, set with your page\'s og:image tag.'],
        ])}
{$this->sources([
            ['Facebook Help Center: cover photo sizes', 'https://www.facebook.com/help/125379114252045'],
            ['Meta Ads Guide: Facebook Feed image ads', 'https://www.facebook.com/business/ads-guide/update/image/facebook-feed'],
            ['Meta for Developers: images in link shares', 'https://developers.facebook.com/docs/sharing/webmasters/images/'],
            ['Profile picture size: widely published guidance (no official page confirmed)', null],
        ])}
{$this->related(['hub', 'instagram', 'og', 'linkedin'])}
HTML;

        return $this->page('facebook', 'Facebook Image Sizes & Requirements for 2026',
            'Feed, profile and cover photo sizes for Facebook, including why the cover shows differently on computers and phones.',
            $body, [
                'meta_title' => 'Facebook Image Sizes & Dimensions 2026 | Softphoria',
                'meta_description' => 'Feed, profile and cover photo sizes for Facebook, including why the cover shows differently on computers and phones and what Facebook actually documents.',
            ], 'Check how Facebook will show your image');
    }

    private function linkedin(): array
    {
        $table = $this->table(['Image', 'Size', 'Status'], [
            ['Post image', '1200 × 627 px (1.91:1) or 1080 × 1080 px', 'Official recommendation for Page posts'],
            ['Link preview', 'At least 1200 × 627 px, 1.91:1, up to 5 MB', 'Official'],
            ['Personal profile photo', '400 × 400 px (shown in a circle), up to 8 MB', 'Third-party recommendation'],
            ['Personal background photo', '1584 × 396 px (4:1)', 'Third-party recommendation'],
            ['Company Page logo', 'Recommended 400 × 400 px, minimum 268 × 268 px', 'Official'],
            ['Company Page cover', '1512 × 256 px', 'Official minimum and recommendation'],
            ['All Company Page images', 'PNG or JPEG, up to 3 MB', 'Platform limit'],
        ]);

        $body = <<<HTML
<p><strong>Quick answer:</strong> use 1200 × 627 px (1.91:1) for post and link-preview images, 400 × 400 px for your profile photo and 1584 × 396 px for your personal background. Company Pages are stricter: the cover is 1512 × 256 px and every Page image must be a PNG or JPEG under 3 MB.</p>
{$this->checkCta('find out whether your image fits LinkedIn\'s Page limits.')}
<h2>LinkedIn image sizes at a glance</h2>
{$table}
<h2>Posts</h2>
<p>LinkedIn recommends a 1.91:1 image (1200 × 627 px) for Page posts; square 1080 × 1080 images are also widely used and take more space in the feed. Images under 200 px wide show as a small thumbnail beside the post.</p>
<h2>Link previews</h2>
<p>When you share a link, LinkedIn shows the page's preview image. LinkedIn's help center lists a minimum of 1200 × 627 px, a 1.91:1 ratio and a 5 MB maximum. Set it with the page's <code>og:image</code> tag — see the <a href="{og}">Open Graph image guide</a>.</p>
<h2>Personal profile: photo and background</h2>
<p>These two are for your personal profile, not a Company Page. LinkedIn doesn't publish them on an official page we could confirm, so they are recommendations: a square profile photo of 400 × 400 px (it is shown in a circle) and a 1584 × 396 px (4:1) background. Sources disagree on the background's file-size limit (4 MB or 8 MB), so keep it under 4 MB to be safe.</p>
<h2>Company Pages</h2>
<p>Company and Career Pages have official, stricter rules: the logo is recommended at 400 × 400 px (minimum 268 × 268), the cover is 1512 × 256 px, and <strong>all Page images must be PNG or JPEG files no larger than 3 MB</strong>. A file over 3 MB won't upload.</p>
<h2>Shape and cropping</h2>
<p>The Page cover is very wide (about 5.9:1) and the personal background is 4:1, so a normal photo loses most of its height. Choose images with a horizontal subject, and keep text clear of the bottom-left, where the profile photo or logo overlaps.</p>
<h2>Common mistakes</h2>
<ul>
<li>Uploading a 4 MB photo as a Company Page cover — over the 3 MB limit.</li>
<li>Using WebP for Page images; LinkedIn requires PNG or JPEG.</li>
<li>Square photos as covers, which lose most of the image.</li>
<li>Link previews smaller than 1200 × 627 that show as a small thumbnail.</li>
</ul>
<h2>Practical recommendations</h2>
<ul>
<li>Export Page images as JPG to stay under 3 MB.</li>
<li>Design link-preview images at 1200 × 627 with text in the centre.</li>
<li>Check covers for the logo and profile-photo overlap.</li>
</ul>
{$this->faq([
            ['What is the best image size for a LinkedIn post?', '1200 × 627 px (1.91:1), which LinkedIn recommends for Page posts. Square 1080 × 1080 px images also work well.'],
            ['What size is a LinkedIn Company Page cover?', '1512 × 256 px, according to LinkedIn\'s help center, as a PNG or JPEG under 3 MB.'],
            ['What size should a LinkedIn profile photo be?', '400 × 400 px is widely recommended. It is shown in a circle, so keep your face centred.'],
            ['What image size does LinkedIn use for link previews?', 'At least 1200 × 627 px at 1.91:1, up to 5 MB.'],
        ])}
{$this->sources([
            ['LinkedIn Help: image specifications for Pages and Career Pages', 'https://www.linkedin.com/help/linkedin/answer/70781'],
            ['LinkedIn Help: make your website shareable on LinkedIn', 'https://www.linkedin.com/help/linkedin/answer/46687'],
            ['Personal profile photo and background: widely published guidance', null],
        ])}
{$this->related(['hub', 'og', 'x', 'facebook'])}
HTML;

        return $this->page('linkedin', 'LinkedIn Image Sizes & Requirements for 2026',
            'Post, link-preview, personal profile and Company Page image sizes for LinkedIn, with the official 3 MB Page limit explained.',
            $body, [
                'meta_title' => 'LinkedIn Image Sizes & Dimensions 2026 | Softphoria',
                'meta_description' => 'Post, profile, background and Company Page image sizes for LinkedIn, with the 3 MB Page limit and 1200 × 627 link previews explained.',
            ], 'Check your image against LinkedIn\'s limits');
    }

    private function x(): array
    {
        $table = $this->table(['Image', 'Size', 'Status'], [
            ['Post image', '1200 × 675 px (16:9), up to 5 MB', 'Third-party recommendation'],
            ['Profile photo', '400 × 400 px, JPEG, GIF or PNG, up to 2 MB', 'Official'],
            ['Header', '1500 × 500 px (3:1); about 60 px at top and bottom may be cropped', 'Official recommendation'],
            ['Link preview (large card)', 'About 2:1, at least 300 × 157 px, under 5 MB', 'Reported from X\'s developer docs (not confirmed)'],
        ]);

        $body = <<<HTML
<p><strong>Quick answer:</strong> on X (formerly Twitter) use 1200 × 675 px (16:9) for post images, a 400 × 400 px profile photo under 2 MB, and a 1500 × 500 px header with nothing important in the top or bottom 60 px.</p>
{$this->checkCta('see how X will crop your header or post image.')}
<h2>X (Twitter) image sizes at a glance</h2>
{$table}
<h2>Post images</h2>
<p>X shows single images in the timeline in a wide frame, so 16:9 (1200 × 675 px) displays without cropping; other shapes are cropped in the preview and shown in full when tapped. A 5 MB limit for photos is widely published, but X's help page for it wasn't available to confirm, so treat it as a recommendation. JPG, PNG and GIF are accepted.</p>
<h2>Profile photo</h2>
<p>X's help center recommends 400 × 400 px and accepts JPEG, GIF or PNG files up to 2 MB. It is shown in a circle.</p>
<h2>Header: the cropping problem</h2>
<p>X recommends 1500 × 500 px (3:1) for headers, but its help center warns that even at that size, about <strong>60 px at the top and bottom can be cropped</strong> depending on the monitor and browser. Your profile photo also overlaps the bottom-left. Keep text and logos in the middle band and treat the top and bottom strips as background.</p>
<h2>Link previews (Twitter cards)</h2>
<p>When you share a link, X builds a card from the page's <code>twitter:image</code> or <code>og:image</code>. For the large-image card (<code>twitter:card</code> set to <code>summary_large_image</code>), X's developer documentation is widely reported to require about 2:1, at least 300 × 157 px and under 5 MB; we couldn't confirm that page directly. A 1200 × 630 px Open Graph image works well in practice — see the <a href="{og}">Open Graph image guide</a>.</p>
<h2>Common mistakes</h2>
<ul>
<li>Header text near the top or bottom edge, which gets cropped.</li>
<li>Profile photos over 2 MB.</li>
<li>Tall portrait images in posts, which are cropped heavily in the timeline preview.</li>
</ul>
<h2>Practical recommendations</h2>
<ul>
<li>Use 16:9 for post images you want shown in full.</li>
<li>Design the header with a clear central band.</li>
<li>Set both <code>og:image</code> and <code>twitter:card</code> on your website.</li>
</ul>
{$this->faq([
            ['What size is a Twitter (X) post image?', '1200 × 675 px (16:9) displays without cropping in the timeline. Other shapes are cropped in the preview.'],
            ['What size is an X header?', '1500 × 500 px. X notes that about 60 px at the top and bottom may be cropped on some screens.'],
            ['What size is an X profile picture?', '400 × 400 px, as a JPEG, GIF or PNG up to 2 MB.'],
            ['Why is my X header cropped?', 'X crops the top and bottom of headers differently on different monitors and browsers. Keep important content in the middle.'],
        ])}
{$this->sources([
            ['X Help Center: profile photo and header', 'https://help.x.com/en/managing-your-account/common-issues-when-uploading-profile-photo'],
            ['Post image size and 5 MB limit: widely published guidance', null],
            ['Large-image card requirements: reported from X\'s developer documentation (page not accessible to confirm)', null],
        ])}
{$this->related(['hub', 'og', 'linkedin', 'facebook'])}
HTML;

        return $this->page('x', 'X (Twitter) Image Sizes & Requirements for 2026',
            'Post images, profile photos, headers and link-preview cards on X (Twitter), with the header cropping issue explained.',
            $body, [
                'meta_title' => 'X (Twitter) Image Sizes & Dimensions 2026 | Softphoria',
                'meta_description' => 'Post images, the 400 × 400 profile photo, the 1500 × 500 header and why its top and bottom get cropped, plus link previews on X (Twitter).',
            ], 'Check your image before you post it on X');
    }

    private function pinterest(): array
    {
        $table = $this->table(['Image', 'Size', 'Status'], [
            ['Standard Pin', '1000 × 1500 px (2:3)', 'Third-party recommendation'],
            ['Minimum for a sharp Pin', '600 × 900 px', 'Third-party recommendation'],
            ['File size', 'Up to 20 MB', 'Third-party recommendation'],
            ['Formats', 'JPG or PNG', 'Third-party recommendation'],
            ['Profile picture', 'Square, at least 400 × 400 px (shown in a circle)', 'Practical guidance'],
        ]);

        $body = <<<HTML
<p><strong>Quick answer:</strong> make Pins 1000 × 1500 px (2:3), as a JPG or PNG. Pinterest's feed is built around vertical images, and 2:3 is the shape the platform and its partners recommend most consistently.</p>
{$this->checkCta('check whether your image will be cropped as a Pin.')}
<h2>Pinterest image sizes at a glance</h2>
{$table}
<p>Pinterest doesn't publish its organic Pin specifications on a single official page we could confirm, so every value here is a recommendation rather than a hard rule.</p>
<h2>Why 2:3</h2>
<p>Pins sit in narrow columns, so vertical images take up more space and get noticed. A 2:3 image (for example 1000 × 1500 px) fills the column without being cut off; very tall images may be shortened in the feed, and square or landscape images look small.</p>
<h2>Resolution and file size</h2>
<p>1000 × 1500 px is sharp on every screen. Go no smaller than about 600 × 900 px. Files up to 20 MB are widely reported to upload, but smaller files load faster — a well-compressed JPG is usually a few hundred KB.</p>
<h2>Formats</h2>
<p>Use JPG for photos and PNG for graphics with text. WebP support for Pin uploads isn't documented consistently, so JPG or PNG is the safer choice.</p>
<h2>Cropping</h2>
<p>An image that isn't 2:3 may be cropped in the feed, usually keeping the middle. Keep text away from the very top and bottom, and preview the crop before publishing.</p>
<h2>Profile picture</h2>
<p>Your profile picture is shown in a circle. A centred square of at least 400 × 400 px displays clearly.</p>
<h2>Common mistakes</h2>
<ul>
<li>Pinning landscape photos, which appear small.</li>
<li>Text-heavy Pins exported at low resolution, which look blurry.</li>
<li>Important text at the very bottom of an extra-tall image.</li>
</ul>
<h2>Practical recommendations</h2>
<ul>
<li>Design every Pin at 1000 × 1500 px.</li>
<li>Use large, readable text in the middle of the Pin.</li>
<li>Export as JPG unless you need sharp graphics, then PNG.</li>
</ul>
{$this->faq([
            ['What size should a Pinterest Pin be?', '1000 × 1500 px, a 2:3 shape, is the most widely recommended size.'],
            ['What aspect ratio is best for Pinterest?', '2:3. It fills the feed column without being cut off.'],
            ['What is the maximum file size for a Pin?', '20 MB is widely reported, but Pinterest doesn\'t publish it on a page we could confirm. Smaller files load faster.'],
            ['Can I upload a square image to Pinterest?', 'Yes, but it appears smaller in the feed than a 2:3 Pin and may be less noticeable.'],
        ])}
{$this->sources([
            ['Pinterest Pin sizes: widely published guidance; no single official specification page found', null],
        ])}
{$this->related(['hub', 'instagram', 'og'])}
HTML;

        return $this->page('pinterest', 'Pinterest Image Sizes & Requirements for 2026',
            'Why Pins work best at 2:3 and 1000 × 1500 px, which formats and file sizes to use, and how Pinterest crops other shapes.',
            $body, [
                'meta_title' => 'Pinterest Image Sizes & Dimensions 2026 | Softphoria',
                'meta_description' => 'Why Pins work best at 2:3 and 1000 × 1500 px, which formats and file sizes to use, and how Pinterest crops other shapes.',
            ], 'Check your Pin before you publish it');
    }

    private function openGraph(): array
    {
        $table = $this->table(['Service', 'What it documents', 'Status'], [
            ['Open Graph protocol', 'Defines og:image, og:image:width, og:image:height and og:image:alt — no required size or shape', 'Official (ogp.me)'],
            ['Facebook / Meta', 'At least 1200 × 630 px recommended, about 1.91:1; minimum 600 × 315 for a large preview; absolute minimum 200 × 200; up to 8 MB', 'Official'],
            ['LinkedIn', 'At least 1200 × 627 px, 1.91:1, up to 5 MB', 'Official'],
            ['X (Twitter)', 'Large-image card about 2:1, at least 300 × 157 px, under 5 MB', 'Reported from X\'s developer docs (not confirmed)'],
        ]);

        $tags = htmlspecialchars('<meta property="og:image" content="https://example.com/images/share.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="A short description of the image">
<meta name="twitter:card" content="summary_large_image">', ENT_QUOTES);

        $body = <<<HTML
<p><strong>Quick answer:</strong> make your Open Graph image 1200 × 630 px (1.91:1), as a JPG or PNG under 5 MB, with the important content in the middle. That size isn't required by the Open Graph protocol — it is the practical default that meets Facebook's and LinkedIn's documented recommendations and works in most apps.</p>
{$this->checkCta('make sure your share image meets Facebook\'s and LinkedIn\'s limits.')}
<h2>What an Open Graph image is</h2>
<p>An Open Graph image is the picture apps show when someone shares your web page — on Facebook, LinkedIn, X, WhatsApp, Slack and many others. You set it in the page's <code>&lt;head&gt;</code> with the <code>og:image</code> tag.</p>
<h2>Who requires what</h2>
{$table}
<p>The Open Graph protocol only defines the tags; it sets no size. Each service decides how to show the image, which is why the numbers differ.</p>
<h2>Why 1200 × 630 is the practical default</h2>
<ul>
<li>It meets Meta's recommendation of at least 1200 × 630 px.</li>
<li>It is just above LinkedIn's 1200 × 627 px minimum, at the same 1.91:1 shape.</li>
<li>It is close to X's 2:1 large card, so very little is cropped there.</li>
</ul>
<h2>The 1.91:1 ratio</h2>
<p>1.91:1 means the image is 1.91 times as wide as it is tall: 1200 ÷ 630 ≈ 1.9. Images of a different shape are cropped to fit the preview, usually from the centre, and some apps show a small square thumbnail instead.</p>
<h2>Formats and file size</h2>
<p>JPG or PNG is the safest choice; not every app shows WebP previews. Facebook accepts files up to 8 MB and LinkedIn up to 5 MB, but a 1200 × 630 JPG is normally well under 500 KB — and smaller files appear faster.</p>
<h2>The tags</h2>
<pre><code>{$tags}</code></pre>
<p>Use an absolute URL (including https://). <code>og:image:width</code> and <code>og:image:height</code> tell apps the image size up front, and <code>og:image:alt</code> describes it for screen readers. The <code>twitter:card</code> tag asks X for the large-image preview; X falls back to <code>og:image</code> for the image itself.</p>
<h2>Safe area and cropping</h2>
<p>Different apps crop the edges slightly differently, and some show a square thumbnail. Keep logos and text in the centre of the image, away from the outer 10% or so, and use large type that is readable at small preview sizes.</p>
<h2>Common mistakes</h2>
<ul>
<li>Using a relative URL in <code>og:image</code>, which many apps can't fetch.</li>
<li>A square or portrait image, which gets cropped to a thin strip.</li>
<li>Text at the edges, which is cut off in some apps.</li>
<li>A WebP or very large image that some apps don't display.</li>
<li>Changing the image but not refreshing the app's cached preview (Facebook and LinkedIn cache previews).</li>
</ul>
<h2>Practical recommendations</h2>
<ul>
<li>Export at 1200 × 630 px as a JPG under 1 MB.</li>
<li>Keep the message in the centre.</li>
<li>Set og:image, og:image:width, og:image:height and og:image:alt on every page.</li>
</ul>
{$this->faq([
            ['What size should an Open Graph image be?', '1200 × 630 px (1.91:1) is the practical default. It meets Facebook\'s and LinkedIn\'s documented recommendations.'],
            ['Is 1200 × 630 required?', 'No. The Open Graph protocol sets no size. 1200 × 630 is a recommendation that works well across Facebook, LinkedIn, X and messaging apps.'],
            ['What aspect ratio is an OG image?', 'About 1.91:1. X\'s large card is about 2:1, which crops only a little from a 1.91:1 image.'],
            ['Where is the Open Graph image used?', 'Anywhere a link to your page is shared and previewed: Facebook, LinkedIn, X, WhatsApp, Slack, Discord and many other apps.'],
            ['What is the minimum Open Graph image size?', 'Facebook requires at least 200 × 200 px and shows a large preview from 600 × 315 px. LinkedIn asks for at least 1200 × 627 px.'],
        ])}
{$this->sources([
            ['The Open Graph protocol (ogp.me)', 'https://ogp.me/'],
            ['Meta for Developers: images in link shares', 'https://developers.facebook.com/docs/sharing/webmasters/images/'],
            ['LinkedIn Help: make your website shareable on LinkedIn', 'https://www.linkedin.com/help/linkedin/answer/46687'],
            ['X large-image card: reported from X\'s developer documentation (page not accessible to confirm)', null],
        ])}
{$this->related(['hub', 'facebook', 'linkedin', 'x'])}
HTML;

        return $this->page('og', 'Open Graph Image Size: 1200 × 630 Explained',
            'What size your og:image should be, why 1200 × 630 is the practical default rather than a protocol rule, and what Facebook, LinkedIn and X each expect.',
            $body, [
                'meta_title' => 'Open Graph Image Size & Requirements 2026 | Softphoria',
                'meta_description' => 'What size your og:image should be, why 1200 × 630 is the practical default (not a protocol rule), and what Facebook, LinkedIn and X each expect.',
            ], 'Check your Open Graph image');
    }
}
