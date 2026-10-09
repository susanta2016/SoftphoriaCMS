<?php

namespace Database\Seeders;

use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Tools\Functionalities\PxToRem;
use Illuminate\Database\Seeder;

/**
 * The PX to REM Converter's landing page (Admin → Tools) as a DRAFT:
 * content, SEO, FAQ and CTA for /tools/px-to-rem-converter. It is never
 * published here — review it, then publish from the admin.
 *
 * The live tool was created in the admin; this seeder records its content
 * (SEO update of 2026-10-09) so a fresh install matches it. Every number in
 * the copy is px ÷ root or rem × root; the conversion table is generated
 * from TABLE_SIZES so it cannot drift from the maths.
 *
 * Never overwrites an admin's work: does nothing if a tool with this slug
 * already exists.
 */
class PxToRemToolSeeder extends Seeder
{
    public const SLUG = 'px-to-rem-converter';

    public const TABLE_SIZES = [1, 2, 4, 8, 10, 12, 14, 16, 18, 20, 24, 28, 32, 40, 48, 64, 80, 96];

    public function run(): void
    {
        if (Tool::query()->where('slug', self::SLUG)->exists()) {
            $this->command?->info('PX to REM Converter already exists — left unchanged.');

            return;
        }

        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->first()
            ?? User::query()->first();

        $tool = new Tool([
            'name' => 'PX to REM Converter',
            'slug' => self::SLUG,
            'tool_category_id' => ToolCategory::query()->where('slug', 'web-development')->value('id'),
            'functionality' => PxToRem::KEY,
            'icon' => 'code',
            'short_description' => 'Convert px to rem and rem to px for any root font size, one value or a whole list, with a reference table.',
            'heading' => 'PX to REM Converter',
            'introduction' => 'Type a pixel value to get its rem equivalent instantly, or type rem to get pixels. Change the root font size if your site doesn\'t use the browser default of 16px, or paste a list of values to convert them all at once.',
            'how_it_works' => <<<'HTML'
<ol>
<li><strong>Set the root font size.</strong> Leave it at 16px unless your CSS sets a different html font size.</li>
<li><strong>Enter px or rem.</strong> Type in either field and the other one updates as you type.</li>
<li><strong>Choose decimal places.</strong> Results are rounded to 2–5 places; the table follows the same setting.</li>
<li><strong>Convert a list.</strong> Paste values separated by new lines or commas, and pick PX → REM or REM → PX.</li>
<li><strong>Copy the result.</strong> Copy one value, or every converted value at once.</li>
</ol>
HTML,
            'use_cases' => null,
            'additional_content' => self::additionalContent(),
            'important_notes' => '<p>If your CSS sets <code>html { font-size: 62.5%; }</code>, the root size is 10px — enter 10 as the root font size.</p>',
            'cta_heading' => 'Building or refreshing a design system?',
            'cta_text' => 'We turn designs into fast, accessible front ends that scale. Tell us about your project.',
            'cta_label' => 'Discuss your project',
            'cta_url' => '/contact',
        ]);
        $tool->status = ToolStatus::Draft;
        $tool->created_by = $actor?->getKey();
        $tool->updated_by = $actor?->getKey();
        $tool->save();

        $tool->seo()->create([
            'meta_title' => 'PX to REM Converter — Free CSS Calculator | Softphoria',
            'meta_description' => 'Convert px to rem or rem to px instantly with a custom root font size. Use the free CSS calculator, bulk conversion and reference table.',
        ]);

        foreach (self::faqs() as $order => [$question, $answer]) {
            $tool->faqs()->create(['question' => $question, 'answer' => $answer, 'is_visible' => true, 'sort_order' => $order]);
        }

        $this->command?->info('PX to REM Converter created as a draft. Review and publish it in Admin → Tools.');
    }

    public static function additionalContent(): string
    {
        $rows = collect(self::TABLE_SIZES)
            ->map(fn (int $px): string => sprintf('<tr><td>%dpx</td><td>%srem</td><td>%srem</td></tr>', $px, self::number($px / 16), self::number($px / 10)))
            ->implode("\n");

        return <<<HTML
<h2>How to Convert PX to REM</h2>
<p>Divide the pixel value by the root font size: <strong>rem = px ÷ root font size</strong>.</p>
<p>The root font size is the font size of the <code>html</code> element. Browsers use 16px by default, but it changes if your CSS sets another size or the visitor changes their browser's default font size, so rem is not fixed at 16px.</p>
<ul>
<li><strong>16px root (browser default).</strong> 24px ÷ 16 = 1.5rem, 14px ÷ 16 = 0.875rem and 40px ÷ 16 = 2.5rem.</li>
<li><strong>10px root.</strong> With <code>html { font-size: 62.5%; }</code> the root is 10px, so 24px ÷ 10 = 2.4rem and 14px ÷ 10 = 1.4rem.</li>
<li><strong>18px root.</strong> 24px ÷ 18 = 1.3333rem, rounded to four decimal places, and 36px ÷ 18 = 2rem.</li>
</ul>
<h2>PX to REM Conversion Table</h2>
<p>Common pixel values at the default 16px root and at a 10px root. For any other root size, change the root font size in the converter and its table updates instantly.</p>
<table>
<thead><tr><th>Pixels</th><th>REM (16px root)</th><th>REM (10px root)</th></tr></thead>
<tbody>
{$rows}
</tbody>
</table>
<h2>PX vs REM: What's the Difference?</h2>
<ul>
<li><strong>px</strong> is an absolute CSS unit. A 24px heading stays 24px whatever font sizes are set elsewhere. A CSS pixel is a reference size, not one screen pixel: high-density screens use several device pixels for each one.</li>
<li><strong>rem</strong> means "root em". It is relative to the root element's font size, so 1rem equals that size: 16px by default. Change the root font size and every rem value scales with it.</li>
<li><strong>em</strong> is relative to the font size of the element itself (for <code>font-size</code>, of its parent), so em values compound when elements are nested. Rem values do not.</li>
</ul>
<p>At a 16px root, 1.5rem and 24px look exactly the same. The difference appears when the root size changes. Browser zoom scales px and rem alike, but the browser's default font size setting only changes sizes set in relative units such as rem.</p>
<h2>When Should You Use REM Instead of PX?</h2>
<ul>
<li><strong>Typography.</strong> Font sizes in rem follow the visitor's preferred default font size, so text can grow for people who need it. Font sizes in px ignore that setting.</li>
<li><strong>Spacing.</strong> Margins, padding and gaps in rem stay in proportion to the text when the root size changes, so larger text does not end up cramped.</li>
<li><strong>Design systems.</strong> Defining type and spacing scales as rem tokens, such as 0.25rem steps (4px at a 16px root), turns a design measured in pixels into consistent, scalable CSS.</li>
</ul>
<h3>When px is still the better choice</h3>
<p>Use px for details that should not grow with the text: 1px borders and dividers, outlines, box shadows, and anything that must match an exact pixel size. Avoid setting the root itself in px, such as <code>html { font-size: 10px; }</code>, because that overrides the visitor's font size preference; a percentage such as 62.5% keeps it.</p>
<p>Using rem does not by itself make a site accessible, but it lets your sizes respect one important user setting. If you are converting a whole site or design system to rem, our <a href="/services/web-development">web development</a> team can help.</p>
<h2>How to Convert REM to PX</h2>
<p>Multiply the rem value by the root font size: <strong>px = rem × root font size</strong>.</p>
<p>With the default 16px root, 1.5rem × 16 = 24px, 0.75rem × 16 = 12px and 2.25rem × 16 = 36px. With a 10px root, 1.5rem × 10 = 15px. Type a rem value in the converter, or choose REM → PX to convert a list.</p>
HTML;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function faqs(): array
    {
        return [
            ['How many rem is 16px?', '1rem with the default 16px root font size (16 ÷ 16 = 1). With a 10px root, 16px is 1.6rem.'],
            ['How many pixels is 1rem?', '1rem equals the root (html) font size: 16px by default in browsers, 10px with a 10px root and 18px with an 18px root.'],
            ['Is 1rem always 16px?', 'No. 1rem is the font size of the root html element. That is 16px by default, but it changes if your CSS sets a different html font size, or if the visitor changes the default font size in their browser (unless your CSS fixes the root size in px).'],
            ['What root font size should I use?', 'Use 16px unless your stylesheet changes the font size of the html element — check your CSS for an html or :root font-size rule.'],
            ['How do I convert px to rem using a 10px root size?', 'Divide by 10: 24px ÷ 10 = 2.4rem and 14px ÷ 10 = 1.4rem. A 10px root usually comes from html { font-size: 62.5%; }, which is 62.5% of 16px. Enter 10 as the root font size in the converter.'],
            ['What is the difference between rem and em?', 'rem is relative to the root element\'s font size, so 1.5rem is the same size everywhere on the page. em is relative to the current element\'s font size (for font-size, its parent\'s), so em values multiply when elements are nested.'],
            ['Why use rem instead of px?', 'Sizes in rem follow the visitor\'s default font size setting in the browser, so text can grow for people who need larger text. Sizes in px ignore that setting. Browser zoom scales both.'],
            ['Can I convert multiple CSS values at once?', 'Yes. Paste the values into the list converter, separated by new lines or commas, and choose PX → REM or REM → PX. Values may include their unit, such as 24px. Copy each result, or copy them all at once.'],
        ];
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
