<?php

namespace Database\Seeders;

use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Tools\Functionalities\ImageRequirements;
use Illuminate\Database\Seeder;

/**
 * The Image Requirements Checker's landing page (Admin → Tools) as a DRAFT:
 * content, SEO and FAQ for /tools/image-requirements-checker. It is never
 * published here — review it, then publish from the admin.
 *
 * Wording rules: platform values are described as platform limits,
 * recommendations or guidance exactly as resources/js/tools/
 * image-requirements/data/profiles.js labels them (reviewed 2026-10-08);
 * the image never leaves the browser. Related tools: only tools that exist.
 *
 * Never overwrites an admin's work: does nothing if a tool with this slug
 * already exists.
 */
class ImageRequirementsToolSeeder extends Seeder
{
    public const SLUG = 'image-requirements-checker';

    public function run(): void
    {
        if (Tool::query()->where('slug', self::SLUG)->exists()) {
            $this->command?->info('Image Requirements Checker already exists — left unchanged.');

            return;
        }

        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        $tool = new Tool([
            'name' => 'Image Requirements Checker',
            'slug' => self::SLUG,
            'tool_category_id' => ToolCategory::query()->where('slug', 'image-media')->value('id'),
            'functionality' => ImageRequirements::KEY,
            'icon' => 'design',
            'short_description' => 'Check image dimensions, aspect ratio, file format, file size, crop compatibility and platform requirements before you publish.',
            'heading' => 'Image Requirements Checker: Size, Format & Crop',
            'introduction' => 'Check an image\'s dimensions, aspect ratio, file size and format against the requirements of Instagram, Facebook, LinkedIn, X, YouTube, Pinterest and websites, or against your own. See which placements it fits, how each one will crop it and what to change, in plain words. Everything runs in your browser: your image is never uploaded.',
            'how_it_works' => <<<'HTML'
<ol>
<li><strong>Add your image.</strong> Choose, drag or paste a JPG, PNG or WebP file. It stays on your device.</li>
<li><strong>See its readiness.</strong> A quick summary of format, resolution, file size and how many placements it fits.</li>
<li><strong>Find where it works.</strong> Every placement is marked Pass, Warning or Fail, grouped into ready to use, works with changes and not suitable.</li>
<li><strong>Understand each problem.</strong> Select a placement to see what is wrong, what is expected and what to do about it.</li>
<li><strong>Preview the crop.</strong> See which part of the image stays visible, drag to try a different position, and check the safe zone where a platform covers or trims the edges.</li>
<li><strong>Check your own requirements.</strong> Enter the size, shape, file size and formats you were given and run the same checks.</li>
</ol>
HTML,
            'use_cases' => <<<'HTML'
<ul>
<li>Checking a photo or graphic before posting it to several social platforms.</li>
<li>Making sure a cover, banner or profile photo will not be cropped badly.</li>
<li>Preparing Open Graph and hero images for a website.</li>
<li>Checking files against a client's or publisher's own size and format rules.</li>
<li>Spotting hidden location data before an image is published.</li>
</ul>
HTML,
            'additional_content' => <<<'HTML'
<h2>What this image checker checks</h2>
<ul>
<li><strong>Dimensions:</strong> width and height in pixels, against each placement's minimum and recommended size.</li>
<li><strong>Aspect ratio:</strong> whether the shape matches the placement, and how much will be cropped if it does not.</li>
<li><strong>File format:</strong> JPG, PNG or WebP, read from the file's content rather than its name.</li>
<li><strong>File size:</strong> against each platform's upload limit.</li>
<li><strong>Resolution:</strong> whether the image is large enough to look sharp, or will be enlarged.</li>
<li><strong>Crop and safe zones:</strong> a preview of what each placement shows, including edges a platform covers or trims.</li>
<li><strong>Transparency:</strong> whether transparent areas may be filled with a solid colour.</li>
<li><strong>Animation:</strong> animated PNG or WebP files, which many placements show as a single frame.</li>
<li><strong>Metadata:</strong> GPS location, EXIF rotation and CMYK colours, when the file includes them.</li>
</ul>
<h2>Supported image requirements</h2>
<p>A platform rarely has one image size: a profile photo, a cover and a post each have their own rules. The checker covers 19 placements:</p>
<ul>
<li><strong>Instagram:</strong> portrait, square and landscape feed posts, Stories and Reel covers</li>
<li><strong>Facebook:</strong> feed posts, cover photo and profile picture</li>
<li><strong>LinkedIn:</strong> post images, profile photo and Company Page cover</li>
<li><strong>X (Twitter):</strong> post images and header</li>
<li><strong>YouTube:</strong> video thumbnails and channel banner</li>
<li><strong>Pinterest:</strong> standard Pins</li>
<li><strong>Websites:</strong> Open Graph link previews, full-width hero images and content images</li>
</ul>
<p>You can also enter your own requirements, such as a form's or publisher's size, shape, file size and format rules. Each platform's sizes are explained in the image size guides below.</p>
<h2>Why check your image before uploading?</h2>
<ul>
<li><strong>No surprise crops:</strong> see what each placement cuts off before anyone else does.</li>
<li><strong>Size problems caught early:</strong> find out whether an image is too small and will look soft, or too large to upload.</li>
<li><strong>Format and file-size issues:</strong> spot a format a platform does not accept, or a file over its limit.</li>
<li><strong>Every platform at once:</strong> check all placements in one go instead of looking up each size.</li>
<li><strong>Safe zones:</strong> see which edges a platform covers or trims on some screens.</li>
<li><strong>Location data:</strong> find GPS location in a photo's metadata before it is published.</li>
</ul>
<h2>Where the requirements come from</h2>
<p><strong>Last reviewed:</strong> October 2026</p>
<p>Each placement's values come from the platform's own help pages where one exists. The checker treats them in three ways, so a recommendation never shows as a failure:</p>
<ul>
<li><strong>Platform limit:</strong> a documented hard limit, such as YouTube's 2048 × 1152 px minimum for channel banners or LinkedIn's 3 MB limit for Page images. Not meeting it is a Fail.</li>
<li><strong>Recommendation:</strong> documented or widely published guidance, such as 1080 × 1350 px for an Instagram portrait post. Not meeting it is a Warning.</li>
<li><strong>Guidance:</strong> useful context, such as Meta's Stories ad safe zone. It never changes the result.</li>
</ul>
<p>Where we could not confirm a value on an official page, it is only ever a recommendation, and each placement lists its sources. Platforms change their requirements without notice, so treat the results as careful guidance and check important images in the app itself.</p>
<h2>Image size guides</h2>
<ul>
<li><a href="/tools/social-media-image-sizes">Social media image sizes</a>: every platform in one table</li>
<li><a href="/tools/instagram-image-sizes">Instagram image sizes</a>: feed, Stories, Reel covers and the 4:5 vs 3:4 question</li>
<li><a href="/tools/youtube-thumbnail-size">YouTube thumbnail size</a>: thumbnails, channel banner and safe area</li>
<li><a href="/tools/facebook-image-sizes">Facebook image sizes</a>: feed, profile and cover photos</li>
<li><a href="/tools/linkedin-image-sizes">LinkedIn image sizes</a>: posts, profiles and Company Pages</li>
<li><a href="/tools/x-twitter-image-sizes">X (Twitter) image sizes</a>: posts, profile photo and header</li>
<li><a href="/tools/pinterest-image-sizes">Pinterest image sizes</a>: the 2:3 Pin</li>
<li><a href="/tools/open-graph-image-size">Open Graph image size</a>: 1200 × 630 explained</li>
</ul>
HTML,
            'important_notes' => <<<'HTML'
<p>Your image is analysed in your browser and is never uploaded or stored.</p>
<p>Platform requirements change over time. The values were last reviewed in October 2026; recommendations are marked as such and never shown as hard failures.</p>
HTML,
        ]);
        $tool->status = ToolStatus::Draft;
        $tool->created_by = $actor?->getKey();
        $tool->updated_by = $actor?->getKey();
        $tool->save();

        $tool->seo()->create([
            'meta_title' => 'Image Requirements Checker: Size, Crop & Format | Softphoria',
            'meta_description' => 'Check image dimensions, aspect ratio, format, file size and crop for Instagram, Facebook, LinkedIn, X, YouTube, Pinterest and websites. Runs in your browser.',
        ]);

        $faqs = [
            ['Is my image uploaded anywhere?', 'No. The checker reads the image in your browser tab. It is never sent to a server or stored.'],
            ['Which formats can I check?', 'JPG, PNG and WebP. HEIC photos from an iPhone, TIFF, GIF and SVG are not checked; export them as JPG or PNG first.'],
            ['What do Pass, Warning and Fail mean?', 'Pass means the image meets the placement\'s requirements. Warning means it is usable but will be cropped, enlarged or is larger than recommended. Fail means it misses a documented platform limit, such as a minimum size or maximum file size.'],
            ['Does the checker crop or resize my image?', 'No. It previews what each placement will show and explains what to change. It never edits your image.'],
            ['Why does it mention location data?', 'Photos from phones can contain GPS location in their metadata. Many platforms remove it on upload, but websites and email usually keep it, so the checker tells you when it is there.'],
            ['How accurate are the platform requirements?', 'They come from the platforms\' own help pages where available and were last reviewed in October 2026. Platforms change their layouts and limits, so use the results as careful guidance and check important images in the app itself.'],
            ['What is an image size checker?', 'A tool that reads an image\'s width, height and file size and compares them with what a platform or website expects. This one also checks the shape, format and crop for each placement.'],
            ['How do I check an image\'s dimensions?', 'Add the image to the checker: its width and height in pixels appear in the summary straight away, with its file size and format.'],
            ['What is the difference between image size and aspect ratio?', 'Image size usually means the dimensions in pixels, such as 1080 × 1350, or the file size in KB or MB. Aspect ratio is the shape: width compared with height, such as 4:5 or 16:9. Two images can share an aspect ratio but have very different sizes.'],
            ['Can I check one image for several platforms?', 'Yes. Every image is checked against all 19 placements at once and grouped into ready to use, works with changes and not suitable.'],
            ['Why does my image fail a platform requirement?', 'A Fail means it misses a documented platform limit, usually a minimum size or a maximum file size. Select the placement to see the expected value, your image\'s value and what to change. Missing a recommendation only gives a Warning.'],
        ];

        foreach ($faqs as $order => [$question, $answer]) {
            $tool->faqs()->create(['question' => $question, 'answer' => $answer, 'is_visible' => true, 'sort_order' => $order]);
        }

        // Related tools: only ones that exist.
        $related = Tool::query()->where('slug', 'social-video-safe-zone-checker')->value('id');
        if ($related) {
            $tool->relatedTools()->attach($related, ['sort_order' => 0]);
        }

        $this->command?->info('Image Requirements Checker created as a draft. Review and publish it in Admin → Tools.');
    }
}
