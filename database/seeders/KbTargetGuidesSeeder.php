<?php

namespace Database\Seeders;

use App\Actions\Page\CreatePageAction;
use App\Enums\PageSectionType;
use App\Enums\PageTemplate;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Eight target-size guides for the Exact Image KB Optimizer
 * (/tools/compress-image-to-10kb … /tools/compress-image-to-1mb) as
 * tool-guide CMS Pages — DRAFTS for review, never Tools-module tools, so
 * they never appear as cards on /tools. Each links to the one canonical
 * tool with its target preselected (?target=50kb).
 *
 * Content varies by size tier and quotes our own measured results (Chrome,
 * October 2026, at the tool's settings). No claims about specific forms'
 * requirements. Visible FAQ only (no FAQPage markup on guides). Author:
 * Softphoria (organisation). Never overwrites an existing slug.
 */
class KbTargetGuidesSeeder extends Seeder
{
    public const TOOL = '/tools/exact-image-kb-optimizer';

    /** slug => [label, param, bytes, photo result, graphic result] */
    public const TARGETS = [
        'compress-image-to-10kb' => ['10 KB', '10kb', 10_000, '318 × 238 px', '465 × 262 px'],
        'compress-image-to-20kb' => ['20 KB', '20kb', 20_000, '527 × 395 px', '756 × 425 px'],
        'compress-image-to-30kb' => ['30 KB', '30kb', 30_000, '687 × 515 px', '1050 × 590 px'],
        'compress-image-to-50kb' => ['50 KB', '50kb', 50_000, '1012 × 759 px', '1592 × 896 px'],
        'compress-image-to-100kb' => ['100 KB', '100kb', 100_000, '1538 × 1154 px', 'the full 1600 × 900 px'],
        'compress-image-to-200kb' => ['200 KB', '200kb', 200_000, '2063 × 1547 px', 'the full 1600 × 900 px'],
        'compress-image-to-500kb' => ['500 KB', '500kb', 500_000, '3062 × 2297 px', 'the full 1600 × 900 px'],
        'compress-image-to-1mb' => ['1 MB', '1mb', 1_000_000, '3977 × 2983 px (almost the full 4000 × 3000)', 'the full 1600 × 900 px'],
    ];

    public function run(): void
    {
        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        if (! $actor) {
            return;
        }

        foreach (array_keys(self::TARGETS) as $slug) {
            if (Page::withTrashed()->where('slug', $slug)->exists()) {
                $this->command?->info("/tools/{$slug} already exists — left unchanged.");

                continue;
            }

            $page = app(CreatePageAction::class)->handle($this->page($slug), $actor);
            $page->forceFill(['author_id' => null])->save();
            $this->command?->info("Created /tools/{$slug} as a draft.");
        }
    }

    /** @return array<string, mixed> */
    private function page(string $slug): array
    {
        [$label, $param, $bytes] = self::TARGETS[$slug];
        $copy = $this->copy($slug);
        $url = self::TOOL.'?target='.$param;

        return [
            'title' => $copy['h1'],
            'slug' => $slug,
            'template' => PageTemplate::Standard->value,
            'is_tool_guide' => true,
            'summary' => $copy['summary'],
            'sections' => [
                ['section_type' => PageSectionType::RichText->value, 'title' => null, 'is_enabled' => true, 'content_json' => ['body' => $this->body($slug, $copy)]],
                ['section_type' => PageSectionType::Cta->value, 'title' => 'Compress your image', 'is_enabled' => true, 'content_json' => [
                    'eyebrow' => 'Free tool',
                    'heading' => "Compress your image to {$label}",
                    'description' => "Opens the Exact Image KB Optimizer with {$label} already selected. Your image is compressed in your browser and never uploaded.",
                    'cta_label' => "Compress to {$label}",
                    'cta_url' => $url,
                ]],
            ],
            'seo' => ['meta_title' => $copy['meta_title'], 'meta_description' => $copy['meta_description']],
        ];
    }

    private function body(string $slug, array $copy): string
    {
        [$label, $param, $bytes, $photo, $graphic] = self::TARGETS[$slug];
        $url = self::TOOL.'?target='.$param;
        $kibi = number_format($bytes === 1_000_000 ? 1_048_576 : $bytes / 1000 * 1024);
        $dec = number_format($bytes);
        $tips = implode("\n", array_map(fn (string $t): string => "<li>{$t}</li>", $copy['tips']));
        $faqs = implode("\n", array_map(fn (array $f): string => "<h3>{$f[0]}</h3>\n<p>{$f[1]}</p>", $copy['faqs']));
        $related = implode("\n", array_map(
            fn (string $s): string => '<li><a href="/tools/'.$s.'">Compress an image to '.self::TARGETS[$s][0].'</a></li>',
            $copy['neighbours'],
        ));

        return <<<HTML
<p><strong>Quick answer:</strong> {$copy['answer']} <a href="{$url}">Open the optimizer with {$label} selected</a> — your image stays on your device.</p>
<h2>How to compress an image to {$label}</h2>
<ol>
<li>Open the <a href="{$url}">Exact Image KB Optimizer</a>; {$label} is already selected.</li>
<li>Add your image: choose, drag or paste a JPG, PNG or WebP file (or several).</li>
<li>Press <strong>Compress to {$label}</strong>. The tool tries the highest quality first and measures every result.</li>
<li>Check the result — size, dimensions, format and a before/after comparison — then download it.</li>
</ol>
<h2>{$copy['fit_heading']}</h2>
<p>{$copy['fit_intro']}</p>
<table>
<thead><tr><th>Image</th><th>Result at {$label}</th></tr></thead>
<tbody>
<tr><td>Detailed 12-megapixel photo (4000 × 3000 px)</td><td>{$photo}, JPEG</td></tr>
<tr><td>Simple graphic with text (1600 × 900 px)</td><td>{$graphic}, JPEG</td></tr>
</tbody>
</table>
<p>Measured with the optimizer in Chrome in October 2026. Your results depend on the image: smooth or simple images keep more pixels than busy, detailed ones.</p>
<h2>Is {$label} counted as {$dec} or {$kibi} bytes?</h2>
<p>Both conventions are in use: some sites count 1 KB as 1,000 bytes, others as 1,024. The optimizer aims under the stricter {$dec} bytes, so the result passes either way. The target is a maximum: the file will be at or below {$label}, usually just under it.</p>
<h2>{$copy['tips_heading']}</h2>
<ul>
{$tips}
</ul>
<h2>{$copy['fail_heading']}</h2>
<p>{$copy['fail']}</p>
<h2>Frequently asked questions</h2>
{$faqs}
<h2>Related</h2>
<ul>
<li><a href="{$url}">Exact Image KB Optimizer ({$label} selected)</a></li>
{$related}
<li><a href="/tools/image-requirements-checker">Image Requirements Checker</a> — check a file against size, shape and format rules</li>
<li><a href="/tools/social-media-image-sizes">Social media image sizes</a></li>
</ul>
HTML;
    }

    /** Target-specific copy, grouped by size tier so pages answer different questions. */
    private function copy(string $slug): array
    {
        $tiny = [
            'tips_heading' => 'Getting a good result at a very small size',
            'tips' => [
                'Crop to what matters first — a signature, a face or a logo — before compressing; every pixel you remove leaves more room for quality.',
                'Use JPEG. PNG is lossless and usually has to shrink a lot to reach a target this small.',
                'For scanned signatures, a clean scan on white paper compresses far better than a photo of a page.',
                'Expect small dimensions: a few hundred pixels across is normal for a photo at this size.',
            ],
            'fail_heading' => 'If the target cannot be reached',
            'fail' => 'Very detailed or noisy images may not fit even at small dimensions. The optimizer then tells you the smallest size it could reach instead of producing a broken file. Cropping the image, or choosing a slightly larger target if the form allows it, usually solves this.',
        ];
        $form = [
            'tips_heading' => 'Tips for photos at this size',
            'tips' => [
                'Keep the subject centred and crop away empty background before compressing.',
                'Check whether the form also limits the width, height or format — set a maximum width and height in the optimizer\'s options if it does.',
                'JPEG gives the best photo quality per kilobyte; keep PNG for graphics with text or transparency.',
                'Use the before/after slider to check faces and text are still sharp.',
            ],
            'fail_heading' => 'If the result looks too soft',
            'fail' => 'At this size a large photo usually needs smaller dimensions. If detail matters, crop to the important part first: a tighter crop keeps more detail in the same number of kilobytes.',
        ];
        $web = [
            'tips_heading' => 'Tips for web and email images',
            'tips' => [
                'Set a maximum width that matches where the image is shown — a blog column rarely needs more than 1200–2000 px.',
                'WebP is often smaller than JPEG at the same quality and suits websites; use JPEG where WebP isn\'t accepted.',
                'Compress all the images for a page at once and download them as a ZIP file.',
                'An image already under the target is left untouched, so it is safe to run a whole folder through.',
            ],
            'fail_heading' => 'If the image still looks too large on screen',
            'fail' => 'File size and display size are different things. If a page shows the image at 800 px wide, set a maximum width of about 1600 px (for sharp high-resolution screens): the file gets smaller and the quality goes up.',
        ];
        $large = [
            'tips_heading' => 'Tips for 1 MB limits',
            'tips' => [
                'Most photos fit under 1 MB at full or almost full size, so the image usually looks the same.',
                'If the limit is per attachment, compress each image separately; batch mode does this for up to 20 images.',
                'PNG screenshots can be large; WebP or JPEG brings them under 1 MB easily.',
                'A photo straight from a modern phone is often 3–6 MB, so it usually needs compressing for 1 MB limits.',
            ],
            'fail_heading' => 'MB and KB',
            'fail' => '1 MB is 1,000 KB in the decimal count (1,000,000 bytes) and 1,024 KB in the binary count (1,048,576 bytes). The optimizer aims under 1,000,000 bytes, which satisfies both.',
        ];

        $pages = [
            'compress-image-to-10kb' => $tiny + [
                'h1' => 'Compress an Image to 10 KB',
                'summary' => 'Make a photo, signature or icon smaller than 10 KB, keeping as much quality as the size allows.',
                'meta_title' => 'Compress Image to 10KB Online — Free, No Upload | Softphoria',
                'meta_description' => 'Shrink a photo, signature or icon to under 10 KB. Keeps the best quality the size allows and shows the result before you download. Nothing is uploaded.',
                'answer' => '10 KB is very small: a photo usually ends up a few hundred pixels wide. Use JPEG, crop to the important part, and let the optimizer find the highest quality that fits.',
                'fit_heading' => 'What fits in 10 KB',
                'fit_intro' => 'At 10 KB a detailed photo has to shrink a lot; simple graphics and signatures keep more pixels.',
                'neighbours' => ['compress-image-to-20kb', 'compress-image-to-30kb'],
                'faqs' => [
                    ['Can a photo really be under 10 KB?', 'Yes, but it will be small — about 300 px across for a detailed photo in our test. Cropping first keeps the subject larger.'],
                    ['Is 10 KB enough for a signature?', 'Usually. A signature on a plain background compresses very well, so it can stay much larger than a photo at the same size.'],
                    ['Why not just lower the JPEG quality more?', 'Below a certain quality, images break up into visible blocks. The optimizer stops at a minimum quality and reduces the dimensions instead.'],
                    ['Does it work for PNG?', 'PNG can only get smaller by shrinking. For 10 KB, JPEG almost always gives a better result; the tool offers to switch.'],
                ],
            ],
            'compress-image-to-20kb' => $tiny + [
                'h1' => 'Compress an Image to 20 KB',
                'summary' => 'Bring a photo, scanned signature or small graphic under 20 KB for an upload limit, at the best quality that fits.',
                'meta_title' => 'Compress Image to 20KB Online — Free Image Size Reducer | Softphoria',
                'meta_description' => 'Reduce an image to under 20 KB for a form or upload limit. Finds the best quality that fits, shows the new size and dimensions, and never uploads your image.',
                'answer' => 'A 20 KB limit is common for small uploads such as signatures and thumbnails. Add your image, keep 20 KB selected and download a JPEG that fits.',
                'fit_heading' => 'What fits in 20 KB',
                'fit_intro' => '20 KB is twice the room of 10 KB, so photos stay noticeably larger and sharper.',
                'neighbours' => ['compress-image-to-10kb', 'compress-image-to-30kb', 'compress-image-to-50kb'],
                'faqs' => [
                    ['How do I reduce a photo to under 20 KB?', 'Open the optimizer with 20 KB selected, add the photo and press Compress. It keeps the highest quality that fits and reduces the dimensions only when needed.'],
                    ['How big will a 20 KB photo be?', 'In our test a detailed 12-megapixel photo came out at 527 × 395 px. Simpler images keep more pixels.'],
                    ['Why is my signature still over 20 KB?', 'Photos of paper pick up shadows and texture. Scan on white, crop tightly and save as JPEG; it then compresses easily.'],
                    ['Will the file be exactly 20 KB?', 'It will be at or below 20 KB, usually just under — a maximum is what upload limits check.'],
                ],
            ],
            'compress-image-to-30kb' => $tiny + [
                'h1' => 'Compress an Image to 30 KB',
                'summary' => 'Fit a photo or graphic under a 30 KB upload limit while keeping it as large and clear as possible.',
                'meta_title' => 'Compress Image to 30KB Online — Free, Keeps Quality | Softphoria',
                'meta_description' => 'Get an image under 30 KB without guesswork: the tool measures every attempt, keeps the best quality that fits and shows the result before you download.',
                'answer' => 'At 30 KB a photo can stay around 700 px wide. Select 30 KB, add the image and download the result once the check shows it passes.',
                'fit_heading' => 'What fits in 30 KB',
                'fit_intro' => '30 KB is enough for a clear small photo or a simple graphic at a useful size.',
                'neighbours' => ['compress-image-to-20kb', 'compress-image-to-50kb'],
                'faqs' => [
                    ['How do I make an image smaller than 30 KB?', 'Open the optimizer with 30 KB selected, add the image and press Compress. You see the new size, dimensions and a before/after comparison.'],
                    ['What dimensions does a 30 KB photo have?', 'Around 687 × 515 px for a detailed photo in our test; it depends on how much detail the image has.'],
                    ['Can I compress several images to 30 KB at once?', 'Yes — add up to 20 images and download them together as a ZIP file.'],
                    ['Does compressing remove location data?', 'Yes. Photo metadata such as camera details and GPS location is not copied into the compressed file.'],
                ],
            ],
            'compress-image-to-50kb' => $form + [
                'h1' => 'Compress an Image to 50 KB',
                'summary' => 'Reduce a photo to under 50 KB — a common limit for online forms — while keeping it as sharp as possible.',
                'meta_title' => 'Compress Image to 50KB Online — Free Photo Compressor | Softphoria',
                'meta_description' => 'Reduce a photo to under 50 KB for an online form or upload. Keeps the highest quality that fits, checks the result and never uploads your photo.',
                'answer' => '50 KB is a common upload limit for profile and application photos. Select 50 KB, add the photo, and the optimizer keeps the highest quality that fits — about 1000 px wide for a detailed photo.',
                'fit_heading' => 'What fits in 50 KB',
                'fit_intro' => 'At 50 KB a photo usually stays around 1000 px wide, and simple graphics can keep almost their full size.',
                'neighbours' => ['compress-image-to-20kb', 'compress-image-to-100kb'],
                'faqs' => [
                    ['How do I compress a photo to 50 KB?', 'Open the optimizer with 50 KB selected, add the photo and press Compress. It finds the highest quality that fits and reduces the dimensions only if needed.'],
                    ['How do I compress a JPG to 50 KB without losing quality?', 'Some quality is always traded for size, but the optimizer keeps as much as possible: it tries the highest quality first and keeps the original dimensions whenever it can. Cropping first helps too.'],
                    ['My form needs 50 KB and a specific size in pixels. Can the tool do both?', 'You can set a maximum width and height in the options. The tool keeps the shape and never enlarges; it does not crop to an exact shape.'],
                    ['Will a 50 KB photo look blurry?', 'At a typical form size it looks sharp. Use the before/after slider to check faces and text before you download.'],
                ],
            ],
            'compress-image-to-100kb' => $form + [
                'h1' => 'Compress an Image to 100 KB',
                'summary' => 'Make a photo smaller than 100 KB for a form, profile or website, with the best quality that fits.',
                'meta_title' => 'Compress Image to 100KB Online — Free, No Upload | Softphoria',
                'meta_description' => 'Shrink a JPG, PNG or WebP image to under 100 KB. Quality first, dimensions only when needed, with a before/after check. Your image never leaves your device.',
                'answer' => 'At 100 KB a detailed photo can stay around 1500 px wide, and most simple images keep their full size. Select 100 KB, add the image and download it.',
                'fit_heading' => 'What fits in 100 KB',
                'fit_intro' => '100 KB is comfortable for a good-quality photo at a size that looks sharp on most screens.',
                'neighbours' => ['compress-image-to-50kb', 'compress-image-to-200kb'],
                'faqs' => [
                    ['How do I reduce an image to 100 KB?', 'Open the optimizer with 100 KB selected, add the image and press Compress. You can also set a maximum width or height and choose JPEG, WebP or PNG.'],
                    ['Is 100 KB a good size for a website image?', 'For content images, yes: it loads quickly and still looks sharp at typical display sizes.'],
                    ['Why did my PNG get much smaller in dimensions?', 'PNG is lossless, so it can only shrink by losing pixels. WebP or JPEG keep far more detail at 100 KB; the tool offers to switch.'],
                    ['Can I compress a whole batch to 100 KB?', 'Yes. Add up to 20 images, compress them in one go and download all the passing ones as a ZIP file.'],
                ],
            ],
            'compress-image-to-200kb' => $web + [
                'h1' => 'Compress an Image to 200 KB',
                'summary' => 'Bring photos under 200 KB for websites, listings and uploads, keeping the quality as high as possible.',
                'meta_title' => 'Compress Image to 200KB Online — Free Image Optimizer | Softphoria',
                'meta_description' => 'Reduce images to under 200 KB for websites, listings or uploads. Keeps large dimensions and high quality, compresses in your browser, batch and ZIP included.',
                'answer' => '200 KB leaves plenty of room: a detailed photo stays around 2000 px wide. Select 200 KB, add one or more images and download the results.',
                'fit_heading' => 'What fits in 200 KB',
                'fit_intro' => 'At 200 KB most photos stay large enough for full-width web use, and simple images keep their full size.',
                'neighbours' => ['compress-image-to-100kb', 'compress-image-to-500kb'],
                'faqs' => [
                    ['How do I compress an image to 200 KB?', 'Open the optimizer with 200 KB selected, add your images and press Compress.'],
                    ['Should I use WebP or JPEG at 200 KB?', 'WebP is often smaller at the same quality and suits websites. If a site or app only accepts JPG, use JPEG.'],
                    ['Will 200 KB images slow down my website?', 'A few are fine. For pages with many images, a maximum width that matches the layout saves even more.'],
                    ['Is the result exactly 200 KB?', 'It is at or below 200 KB, usually just under.'],
                ],
            ],
            'compress-image-to-500kb' => $web + [
                'h1' => 'Compress an Image to 500 KB',
                'summary' => 'Reduce large photos to under 500 KB while keeping them big and sharp — for websites, email and upload limits.',
                'meta_title' => 'Compress Image to 500KB Online — Free, Keeps Detail | Softphoria',
                'meta_description' => 'Get large photos under 500 KB while keeping them big and sharp. Measures every attempt, never exceeds your limit and never uploads your images.',
                'answer' => 'At 500 KB even a detailed 12-megapixel photo stays about 3000 px wide, so it still looks sharp on large screens. Select 500 KB, add the image and download it.',
                'fit_heading' => 'What fits in 500 KB',
                'fit_intro' => 'Half a megabyte keeps most photos close to their original size.',
                'neighbours' => ['compress-image-to-200kb', 'compress-image-to-1mb'],
                'faqs' => [
                    ['How do I reduce a photo to 500 KB?', 'Open the optimizer with 500 KB selected, add the photo and press Compress. It keeps the dimensions whenever quality alone is enough.'],
                    ['Is 500 KB too large for a web page?', 'For a single hero image it is acceptable; for galleries, smaller targets such as 200 KB load faster.'],
                    ['Can I compress phone photos to 500 KB?', 'Yes. JPEG photos from phones compress well; HEIC photos need to be exported as JPG first.'],
                    ['What if my image is already under 500 KB?', 'It is left exactly as it is, unless you change the format or set a maximum size.'],
                ],
            ],
            'compress-image-to-1mb' => $large + [
                'h1' => 'Compress an Image to 1 MB',
                'summary' => 'Get photos and screenshots under 1 MB for email, forms and upload limits, usually at their full size.',
                'meta_title' => 'Compress Image to 1MB Online — Free, Full Size Kept | Softphoria',
                'meta_description' => 'Reduce a photo or screenshot to under 1 MB, usually at full size. Works in your browser, handles batches and never uploads your images.',
                'answer' => 'Most photos fit under 1 MB at full or almost full size. Select 1 MB, add the image and download a version that passes the limit.',
                'fit_heading' => 'What fits in 1 MB',
                'fit_intro' => 'At 1 MB the optimizer rarely needs to touch the dimensions at all.',
                'neighbours' => ['compress-image-to-500kb', 'compress-image-to-200kb'],
                'faqs' => [
                    ['How do I compress an image to 1 MB?', 'Open the optimizer with 1 MB selected, add the image and press Compress.'],
                    ['Will my photo lose its size at 1 MB?', 'Usually not: in our test a detailed 12-megapixel photo kept almost its full 4000 × 3000 px.'],
                    ['How many KB is 1 MB?', '1,000 KB in the decimal count or 1,024 KB in the binary count. The optimizer aims under 1,000,000 bytes, which passes both.'],
                    ['Can I compress several images to 1 MB each?', 'Yes — each image is compressed to the limit separately, and you can download them all as a ZIP file.'],
                ],
            ],
        ];

        return $pages[$slug];
    }
}
