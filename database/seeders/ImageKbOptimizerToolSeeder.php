<?php

namespace Database\Seeders;

use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Tools\Functionalities\ImageKbOptimizer;
use Illuminate\Database\Seeder;

/**
 * The Exact Image KB Optimizer's landing page (Admin → Tools) as a DRAFT:
 * /tools/exact-image-kb-optimizer. Never publishes, never overwrites a tool
 * that already exists. Related tools: only ones that exist.
 *
 * Wording rules: a target is a maximum ("under 50 KB"), never promised as an
 * exact byte count; no claims about specific forms' requirements.
 */
class ImageKbOptimizerToolSeeder extends Seeder
{
    public const SLUG = 'exact-image-kb-optimizer';

    public function run(): void
    {
        if (Tool::query()->where('slug', self::SLUG)->exists()) {
            $this->command?->info('Exact Image KB Optimizer already exists — left unchanged.');

            return;
        }

        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        $tool = new Tool([
            'name' => 'Exact Image KB Optimizer',
            'slug' => self::SLUG,
            'tool_category_id' => ToolCategory::query()->where('slug', 'image-media')->value('id'),
            'functionality' => ImageKbOptimizer::KEY,
            'icon' => 'design',
            'short_description' => 'Compress an image to under 20 KB, 50 KB, 100 KB or any size you choose, keeping the best quality possible. Runs in your browser.',
            'heading' => 'Compress Image to Any KB Size Online',
            'introduction' => 'Need a photo under 20 KB, 50 KB or 100 KB for an online form? Choose a common maximum size or enter any custom KB or MB limit, add your image and download a version that fits — at the highest quality that still passes. Your images are compressed in your browser and never uploaded.',
            'how_it_works' => <<<'HTML'
<ol>
<li><strong>Add your image.</strong> Choose, drag or paste one or more JPG, PNG or WebP images.</li>
<li><strong>Choose the maximum size.</strong> Pick 10 KB to 1 MB, or type any size in KB or MB.</li>
<li><strong>Compress.</strong> The tool tries the highest quality first and measures every result, keeping the original dimensions whenever it can.</li>
<li><strong>Check the result.</strong> See the new size, dimensions and format, compare before and after, and confirm it passes your limit.</li>
<li><strong>Download.</strong> Save one image, or all of them as a ZIP file.</li>
</ol>
HTML,
            // Use cases live in the "Why compress…" part of the additional content,
            // so the page has one heading per topic.
            'use_cases' => null,
            'additional_content' => <<<'HTML'
<h2>Why Compress an Image to a Specific KB Size?</h2>
<h3>Online forms and applications</h3>
<p>Some upload forms set a maximum image file size, such as 20 KB, 50 KB or 100 KB, and reject anything larger. Choose that limit here and the result is checked against it before you download.</p>
<h3>Photos and signatures</h3>
<p>Small file-size limits are common when uploading a photograph or a scanned signature to an application form. The tool keeps the highest quality that still fits, so faces and handwriting stay as clear as the limit allows.</p>
<h3>Websites, email and sharing</h3>
<p>Smaller images are quicker to upload, send and store, and keep the images on a website light. You can compress up to 20 images to the same limit at once and download them together as a ZIP file.</p>
<h2>How the Size Target Works</h2>
<p>The size you choose is a <strong>maximum</strong>. "50 KB" means the result will be at or below 50 KB — usually just under it, because that leaves the most room for quality. You can pick a common size or enter any custom limit in KB or MB.</p>
<ul>
<li><strong>Passes either way of counting:</strong> some sites count 1 KB as 1,000 bytes, others as 1,024. We aim under the stricter 1,000-byte count, so the image passes both.</li>
<li><strong>Quality before dimensions:</strong> the tool first lowers the JPEG or WebP quality, and only reduces the width and height when that is not enough. It never enlarges an image.</li>
<li><strong>Honest results:</strong> if a target is too small to reach without destroying the image, you are told the smallest size that was possible instead of getting a broken file.</li>
</ul>
<h2>Compress Image to 20KB, 50KB, 100KB and More</h2>
<p>Choose a common maximum file size below, or use the optimizer to enter any custom KB or MB limit. Each guide explains the target size and opens the same browser-based optimizer with that limit preselected.</p>
<ul>
<li><a href="/tools/compress-image-to-10kb">Compress an image to 10 KB</a></li>
<li><a href="/tools/compress-image-to-20kb">Compress an image to 20 KB</a></li>
<li><a href="/tools/compress-image-to-30kb">Compress an image to 30 KB</a></li>
<li><a href="/tools/compress-image-to-50kb">Compress an image to 50 KB</a></li>
<li><a href="/tools/compress-image-to-100kb">Compress an image to 100 KB</a></li>
<li><a href="/tools/compress-image-to-200kb">Compress an image to 200 KB</a></li>
<li><a href="/tools/compress-image-to-500kb">Compress an image to 500 KB</a></li>
<li><a href="/tools/compress-image-to-1mb">Compress an image to 1 MB</a></li>
</ul>
<h2>Compress JPG, PNG and WebP Images</h2>
<p>Use the optimizer to reduce JPG, JPEG, PNG or WebP images to a maximum file size such as 20 KB, 50 KB, 100 KB or 200 KB. The tool measures the actual encoded output size rather than estimating it. For JPG and WebP, it searches for the highest quality that fits the selected limit, and reduces the dimensions only when the lowest quality setting is still too large.</p>
<p>PNG images are handled as lossless images, so a PNG can only get smaller by reducing its dimensions, and a very small target is not always reachable as PNG. When a much smaller file is required, the tool offers WebP (which keeps transparency) or JPEG (which fills transparent areas with white) — it never changes the format without telling you.</p>
HTML,
            'important_notes' => <<<'HTML'
<p>Your images are processed in your browser and are never uploaded or stored.</p>
<p>Photo metadata such as camera details and location is not copied into compressed images. Check the requirements of the form or site you are uploading to; some also limit the width, height or format.</p>
HTML,
        ]);
        $tool->status = ToolStatus::Draft;
        $tool->created_by = $actor?->getKey();
        $tool->updated_by = $actor?->getKey();
        $tool->save();

        $tool->seo()->create([
            'meta_title' => 'Compress Image to Any KB Size Online — 20KB, 50KB, 100KB | Softphoria',
            'meta_description' => 'Compress JPG, PNG or WebP images to 20KB, 50KB, 100KB or any custom size online. Optimize quality, verify the final size and download privately in your browser.',
        ]);

        $faqs = [
            ['How do I compress an image to 50 KB?', 'Add your image, choose 50 KB and press Compress. The tool finds the highest quality that keeps the file at or below 50 KB, and reduces the dimensions only if quality alone is not enough.'],
            ['Will the file be exactly 50 KB?', 'It will be at or below 50 KB, usually just under. Forms set a maximum, so a slightly smaller file always passes.'],
            ['Is my image uploaded to a server?', 'No. Compression happens entirely in your browser, and ZIP files are created on your device too.'],
            ['Why did the dimensions change?', 'Some targets cannot be reached at a reasonable quality at full size. The tool then reduces the width and height step by step, keeping the shape, and tells you the new dimensions.'],
            ['Can I compress a PNG to a small size?', 'PNG is lossless, so it can only shrink by reducing its dimensions. For small targets, WebP or JPEG usually give a much better result; the tool offers to switch and warns you if transparency would be lost.'],
            ['Can I compress several images at once?', 'Yes. Add up to 20 images, compress them all to the same target, and download them one by one or together as a ZIP file.'],
            ['Does it count 1 KB as 1,000 or 1,024 bytes?', 'It aims under the stricter 1,000-byte count, so the result passes whichever way the site you upload to counts kilobytes.'],
        ];

        foreach ($faqs as $order => [$question, $answer]) {
            $tool->faqs()->create(['question' => $question, 'answer' => $answer, 'is_visible' => true, 'sort_order' => $order]);
        }

        $related = Tool::query()->whereIn('slug', ['image-requirements-checker', 'social-video-safe-zone-checker'])->orderByRaw("slug = 'image-requirements-checker' desc")->pluck('id');
        foreach ($related as $order => $id) {
            $tool->relatedTools()->attach($id, ['sort_order' => $order]);
        }

        $this->command?->info('Exact Image KB Optimizer created as a draft. Review and publish it in Admin → Tools.');
    }
}
