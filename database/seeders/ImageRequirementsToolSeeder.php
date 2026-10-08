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
            'heading' => 'Image Requirements Checker',
            'introduction' => 'Upload an image and see straight away where it works: Instagram, Facebook, LinkedIn, X, YouTube, Pinterest and your website. The checker explains every problem in plain words, previews how each placement will crop it, and runs entirely in your browser — your image is never uploaded.',
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
<h2>Where the requirements come from</h2>
<p><strong>Last reviewed:</strong> October 2026</p>
<p>Each placement's values come from the platform's own help pages where one exists. The checker treats them in three ways, so a recommendation never shows as a failure:</p>
<ul>
<li><strong>Platform limit:</strong> a documented hard limit, such as YouTube's 2048 × 1152 px minimum for channel banners or LinkedIn's 3 MB limit for Page images. Not meeting it is a Fail.</li>
<li><strong>Recommendation:</strong> documented or widely published guidance, such as 1080 × 1350 px for an Instagram portrait post. Not meeting it is a Warning.</li>
<li><strong>Guidance:</strong> useful context, such as Meta's Stories ad safe zone. It never changes the result.</li>
</ul>
<p>Where we could not confirm a value on an official page, it is only ever a recommendation, and each placement lists its sources. Platforms change their requirements without notice, so treat the results as careful guidance and check important images in the app itself.</p>
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
