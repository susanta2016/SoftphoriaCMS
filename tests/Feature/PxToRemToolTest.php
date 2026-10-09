<?php

namespace Tests\Feature;

use App\Enums\ToolStatus;
use App\Models\Service;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Tools\ToolPublisher;
use Database\Seeders\PxToRemToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * PX to REM Converter landing page: create-only draft seeder, published page
 * SEO, heading outline, a conversion table that matches the maths, internal
 * links, the server-rendered tool and its no-JavaScript fallback. The
 * conversion logic itself is covered by resources/js/tools/px-to-rem/tests.
 */
class PxToRemToolTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    private const URL = '/tools/px-to-rem-converter';

    public function test_the_seeder_creates_an_unpublished_draft_and_never_overwrites_it(): void
    {
        $tool = $this->seededTool();

        $this->assertSame(ToolStatus::Draft, $tool->status);
        $this->assertSame(8, $tool->faqs()->where('is_visible', true)->count());
        $this->assertSame([], app(ToolPublisher::class)->missingRequirements($tool));
        $this->get(self::URL)->assertNotFound();

        $tool->update(['heading' => 'Edited by an admin']);
        $this->seed(PxToRemToolSeeder::class);

        $this->assertSame('Edited by an admin', $tool->refresh()->heading);
        $this->assertSame(8, $tool->faqs()->count());
        $this->assertSame(1, Tool::query()->where('slug', 'px-to-rem-converter')->count());
    }

    public function test_the_published_page_has_the_converter_and_full_seo(): void
    {
        $html = $this->publishedHtml();

        $this->assertStringContainsString('<title>PX to REM Converter — Free CSS Calculator | Softphoria</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Convert px to rem or rem to px instantly with a custom root font size. Use the free CSS calculator, bulk conversion and reference table.">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url(self::URL).'">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertSame(1, preg_match_all('#<h1[\s>]#', $html));
        $this->assertMatchesRegularExpression('#<h1[^>]*>PX to REM Converter</h1>#', $html);
        $this->assertStringContainsString('"softwareVersion":"1.2.0"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertSame(8, substr_count($html, '"@type":"Question"'));
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertSame(1, substr_count($html, 'application/ld+json'));

        preg_match_all('# id="([^"]+)"#', $html, $ids);
        $this->assertSame(array_unique($ids[1]), $ids[1], 'no duplicate ids');
    }

    public function test_the_content_follows_the_heading_plan(): void
    {
        $html = $this->publishedHtml();
        $main = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));

        preg_match_all('#<(h[1-6])[^>]*>(.*?)</h[1-6]>#s', $main, $headings, PREG_SET_ORDER);
        $outline = array_map(fn (array $h): string => $h[1].' '.trim(html_entity_decode(strip_tags($h[2]), ENT_QUOTES)), $headings);

        $this->assertSame([
            'h1 PX to REM Converter',
            'h2 How it works',
            'h2 How to Convert PX to REM',
            'h2 PX to REM Conversion Table',
            "h2 PX vs REM: What's the Difference?",
            'h2 When Should You Use REM Instead of PX?',
            'h3 When px is still the better choice',
            'h2 How to Convert REM to PX',
            'h2 Common questions',
            'h2 Building or refreshing a design system?',
        ], $outline, 'the tool interface adds no headings');
    }

    public function test_the_conversion_table_matches_the_formula(): void
    {
        $html = $this->publishedHtml();

        foreach (PxToRemToolSeeder::TABLE_SIZES as $px) {
            $at16 = rtrim(rtrim(number_format($px / 16, 4, '.', ''), '0'), '.');
            $at10 = rtrim(rtrim(number_format($px / 10, 4, '.', ''), '0'), '.');
            $this->assertStringContainsString("<tr><td>{$px}px</td><td>{$at16}rem</td><td>{$at10}rem</td></tr>", $html);
        }
        $this->assertStringContainsString('<tr><td>14px</td><td>0.875rem</td><td>1.4rem</td></tr>', $html);
        $this->assertStringContainsString('<tr><td>96px</td><td>6rem</td><td>9.6rem</td></tr>', $html);
    }

    public function test_the_tool_renders_without_javascript(): void
    {
        $html = $this->publishedHtml();

        // Reference table at 16px, server-rendered with the requested values
        foreach ([1 => '0.0625', 14 => '0.875', 80 => '5', 96 => '6'] as $px => $rem) {
            $this->assertStringContainsString("data-px=\"{$px}\">{$rem}rem</td>", $html);
        }
        $this->assertStringContainsString('<noscript>', $html);
        $this->assertStringContainsString('px = rem × root font size', $html);
        // The list converter needs the script, so it starts hidden
        $this->assertMatchesRegularExpression('#<div[^>]*hidden data-px-rem-bulk>#', $html);
        $this->assertStringContainsString('<label for="pxrem-precision"', $html);
        $this->assertStringContainsString('<label for="pxrem-list"', $html);
        // Copy all (one per line) and a space-separated CSS value, both explained
        $this->assertStringContainsString('data-px-rem-copy-all', $html);
        $this->assertMatchesRegularExpression('#<button[^>]*aria-describedby="pxrem-css-help"[^>]*data-px-rem-copy-css>#', $html);
        $this->assertStringContainsString('separated by new lines, commas or spaces', $html);
    }

    public function test_internal_links_resolve(): void
    {
        Service::query()->create(['title' => 'Web Development', 'slug' => 'web-development', 'summary' => 'Sites.', 'is_published' => true]);
        $html = $this->publishedHtml();
        $main = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));

        preg_match_all('#href="(/[^"]*)"#', $main, $links);
        $this->assertContains('/services/web-development', $links[1]);
        foreach (array_unique($links[1]) as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_seeded_fields_fit_the_admin_limits(): void
    {
        $tool = $this->seededTool();

        $this->assertLessThanOrEqual(60, mb_strlen($tool->seo->meta_title));
        $this->assertLessThanOrEqual(160, mb_strlen($tool->seo->meta_description));
        $this->assertLessThanOrEqual(600, mb_strlen($tool->introduction));
        foreach (PxToRemToolSeeder::faqs() as [$question, $answer]) {
            $this->assertLessThanOrEqual(200, mb_strlen($question));
            $this->assertLessThanOrEqual(2000, mb_strlen($answer));
        }
    }

    private function publishedHtml(): string
    {
        $tool = $this->seededTool();
        $this->assertSame([], app(ToolPublisher::class)->publish($tool, null));

        return $this->get(self::URL)->assertOk()->getContent();
    }

    private function seededTool(): Tool
    {
        ToolCategory::query()->firstOrCreate(['slug' => 'web-development'], ['name' => 'Web Development']);
        $this->seed(PxToRemToolSeeder::class);

        return Tool::query()->where('slug', 'px-to-rem-converter')->sole();
    }
}
