<?php

namespace Tests\Feature;

use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Tools\Functionalities\SocialVideoSafeZone;
use App\Tools\ToolPublisher;
use App\Tools\ToolRegistry;
use Database\Seeders\SocialVideoSafeZoneToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * P1b: the Social Video Safe Zone Checker as a Softphoria Tool — discovered
 * functionality, draft landing-page content, preview and public page, SEO,
 * and no server-side upload path (the checker runs in the browser only).
 */
class SocialVideoSafeZoneToolTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    private const URL = '/tools/social-video-safe-zone-checker';

    public function test_the_registry_discovers_the_checker_with_its_view_and_script(): void
    {
        $functionality = app(ToolRegistry::class)->find('social-video-safe-zone');

        $this->assertInstanceOf(SocialVideoSafeZone::class, $functionality);
        $this->assertSame('Social Video Safe Zone Checker (v1.0.0)', app(ToolRegistry::class)->options()['social-video-safe-zone']);
        $this->assertSame('MultimediaApplication', $functionality->applicationCategory());
        $this->assertSame(['resources/js/tools/social-video-safe-zone.js'], $functionality->assets());
        $this->assertTrue(view()->exists($functionality->view()));
    }

    public function test_the_seeder_creates_an_unpublished_draft_and_never_overwrites_it(): void
    {
        $this->category();
        $this->seed(SocialVideoSafeZoneToolSeeder::class);

        $tool = Tool::query()->where('slug', 'social-video-safe-zone-checker')->sole();
        $this->assertSame(ToolStatus::Draft, $tool->status);
        $this->assertNull($tool->published_at);
        $this->assertSame('social-video-safe-zone', $tool->functionality);
        $this->assertSame(5, $tool->faqs()->where('is_visible', true)->count());
        $this->assertSame([], app(ToolPublisher::class)->missingRequirements($tool), 'ready to publish from the admin');
        $this->get(self::URL)->assertNotFound();

        $tool->update(['heading' => 'Edited by an admin']);
        $this->seed(SocialVideoSafeZoneToolSeeder::class);
        $this->assertSame('Edited by an admin', $tool->refresh()->heading);
        $this->assertSame(1, Tool::query()->where('slug', 'social-video-safe-zone-checker')->count());
    }

    public function test_the_content_makes_no_unsupported_claims(): void
    {
        $this->category();
        $this->seed(SocialVideoSafeZoneToolSeeder::class);
        $tool = Tool::query()->where('slug', 'social-video-safe-zone-checker')->sole();

        $text = strtolower(implode(' ', [
            $tool->short_description, $tool->introduction, $tool->how_it_works, $tool->use_cases,
            $tool->additional_content, $tool->important_notes, $tool->seo->meta_title, $tool->seo->meta_description,
            ...$tool->faqs->flatMap(fn ($faq): array => [$faq->question, $faq->answer]),
        ]));

        foreach (['100%', 'guarantee safe', 'guaranteed', 'always accurate', 'fully accurate', 'tiktok measured'] as $claim) {
            $this->assertStringNotContainsString($claim, $text, $claim);
        }
        $this->assertStringContainsString('provisional', $text);
        $this->assertStringContainsString('never uploaded', $text);
    }

    public function test_admins_preview_the_checker_noindexed(): void
    {
        $tool = $this->seededTool();

        $this->actingAs($this->admin())->get($tool->previewUrl())
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('data-tool-preview="true"', false)
            ->assertSee('data-svsz-root data-theme="light"', false);
    }

    public function test_the_published_page_has_the_checker_and_full_seo(): void
    {
        $tool = $this->seededTool();
        $this->assertSame([], app(ToolPublisher::class)->publish($tool, null));

        $html = $this->get(self::URL)->assertOk()->getContent();

        $this->assertStringContainsString('data-svsz-root', $html);
        $this->assertStringContainsString('data-tool-functionality="social-video-safe-zone"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url(self::URL).'">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('"@type":"WebApplication"', $html);
        $this->assertStringContainsString('"applicationCategory":"MultimediaApplication"', $html);
        $this->assertStringContainsString('"softwareVersion":"1.0.0"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringNotContainsString('multipart/form-data', $html);
        $this->assertStringNotContainsString('data-px-rem', $html);
        $this->assertStringNotContainsString('data-svsz-root', $this->get('/')->getContent());
    }

    public function test_there_is_no_server_side_upload_path_for_the_checker(): void
    {
        $writes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH']) !== [])
            ->filter(fn (Route $route): bool => str_contains($route->uri(), 'tools') || str_contains($route->uri(), 'safe-zone') || str_contains($route->uri(), 'svsz'))
            ->map(fn (Route $route): string => $route->uri())
            ->values()->all();

        $this->assertSame([], $writes);
    }

    private function category(): ToolCategory
    {
        return ToolCategory::query()->firstOrCreate(['slug' => 'image-media'], ['name' => 'Image & Media']);
    }

    private function seededTool(): Tool
    {
        $this->category();
        $this->seed(SocialVideoSafeZoneToolSeeder::class);

        return Tool::query()->where('slug', 'social-video-safe-zone-checker')->sole();
    }
}
