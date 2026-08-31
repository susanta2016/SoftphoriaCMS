<?php

namespace Tests\Feature;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Client requirement: the existing "about" CMS Page (App\Models\Page /
 * App\Models\PageSection, same architecture as every other Page) must
 * carry three distinct rich_text sections — About All the Things Light,
 * About Cory Gold, About Jacob d'IAWARII — in that order, rendered through
 * the existing pages.show/PageContentRenderer pipeline. See
 * database/seeders/AboutPageSeeder.php for what actually seeds the real
 * "about" page; these tests build their own Page fixture so they don't
 * depend on that seeded content ever having run.
 */
class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_about_page_still_resolves_and_renders_its_three_sections_in_order(): void
    {
        $page = $this->aboutPage();

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSeeInOrder([
            'About All the Things Light',
            'About Cory Gold',
            'About Jacob d&#039;IAWARII',
        ], false);
    }

    public function test_content_is_read_from_the_page_section_data_not_hardcoded_in_blade(): void
    {
        $page = $this->aboutPage(bodyOverride: '<p>A distinctive sentinel paragraph unique to this test run.</p>');

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('A distinctive sentinel paragraph unique to this test run.');
    }

    public function test_the_all_the_things_light_section_renders_the_supplied_content_exactly(): void
    {
        $page = $this->aboutPage();

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('All the Things Light is a place to come and gather.', false);
        $response->assertSee('Love is creation’s greatest idea.', false);
        $response->assertSee('We are one.', false);
        $response->assertSee('The closer we move toward the light, the more we begin to look like the light.', false);
        $response->assertSee('Come and Gather. ✨', false);
    }

    public function test_the_cory_gold_section_renders_its_title_with_no_fabricated_content(): void
    {
        $page = $this->aboutPage();

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('About Cory Gold', false);

        $coryBlock = $this->extractSectionHtml($response->getContent(), 'About Cory Gold');
        $this->assertSame('', trim(strip_tags($coryBlock)));
    }

    public function test_the_jacob_section_renders_its_title_with_no_fabricated_written_content(): void
    {
        $page = $this->aboutPage();

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();

        $jacobBlock = $this->extractSectionHtml($response->getContent(), 'About Jacob d&#039;IAWARII');
        // Only the <video> markup should be present as content — no invented biography text.
        $this->assertSame('', trim(strip_tags($jacobBlock)));
    }

    public function test_jacobs_video_references_the_existing_media_record_and_streams_through_the_existing_route(): void
    {
        $video = $this->storeVideo();
        $page = $this->aboutPage(videoMediaId: $video->id);

        $section = $page->sections()->where('title', "About Jacob d'IAWARII")->firstOrFail();
        $this->assertSame($video->id, $section->content_json['video_media_id']);

        $response = $this->get(route('pages.show', $page));
        $response->assertOk();
        $response->assertSee(route('media.watch', $video), false);

        $streamResponse = $this->get(route('media.watch', $video));
        $streamResponse->assertOk();
        $streamResponse->assertHeader('Content-Type', 'video/mp4');
    }

    public function test_the_about_page_renders_no_video_player_when_jacobs_section_has_no_video_selected(): void
    {
        $page = $this->aboutPage(videoMediaId: null);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertDontSee('<video', false);
    }

    public function test_a_video_not_attached_to_a_published_section_is_not_publicly_streamable(): void
    {
        $video = $this->storeVideo();
        // Deliberately not attached to any section.

        $response = $this->get(route('media.watch', $video));

        $response->assertNotFound();
    }

    private function extractSectionHtml(string $html, string $title): string
    {
        $start = strpos($html, $title);
        $this->assertNotFalse($start, "Section title \"{$title}\" not found in response.");

        $afterHeading = strpos($html, '</h2>', $start) + strlen('</h2>');
        $nextSection = strpos($html, '<section class="block">', $afterHeading);
        $mainEnd = strpos($html, '</main>', $afterHeading);
        $end = min(array_filter([$nextSection, $mainEnd, strlen($html)], fn ($value) => $value !== false));

        return substr($html, $afterHeading, $end - $afterHeading);
    }

    private function aboutPage(?string $bodyOverride = null, int|false|null $videoMediaId = false): Page
    {
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about-test-'.uniqid(),
            'template' => PageTemplate::Standard->value,
            'status' => PageStatus::Published->value,
        ]);

        $jacobVideoId = $videoMediaId === false ? $this->storeVideo()->id : $videoMediaId;

        $page->sections()->create([
            'section_type' => PageSectionType::RichText->value,
            'title' => 'About All the Things Light',
            'sort_order' => 0,
            'is_enabled' => true,
            'content_json' => [
                'body' => $bodyOverride ?? $this->allTheThingsLightBody(),
            ],
        ]);

        $page->sections()->create([
            'section_type' => PageSectionType::RichText->value,
            'title' => 'About Cory Gold',
            'sort_order' => 1,
            'is_enabled' => true,
            'content_json' => ['body' => ''],
        ]);

        $page->sections()->create([
            'section_type' => PageSectionType::RichText->value,
            'title' => "About Jacob d'IAWARII",
            'sort_order' => 2,
            'is_enabled' => true,
            'content_json' => [
                'body' => '',
                'video_media_id' => $jacobVideoId,
            ],
        ]);

        return $page;
    }

    private function storeVideo(): Media
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/video/test-jacob.mp4', 'fake-mp4-bytes');

        return app(StoreUploadedMediaAction::class)->handle('local', 'media/video/test-jacob.mp4', User::factory()->create(), 'protected');
    }

    private function allTheThingsLightBody(): string
    {
        return <<<'HTML'
            <p>All the Things Light is a place to come and gather.</p>
            <p>A gathering place for inspired music, meaningful conversation, reflection, gratitude, and connection—created around a simple belief: there is always light to notice, light to share, and light to become.</p>
            <p>Here, music can carry a message. Conversation can open a new way of seeing. Gratitude can turn attention toward what is already present. A few words can become a Light Post, offering encouragement, hope, or reflection for the gathering.</p>
            <p>All the Things Light is not about having all the answers. It is about making room—to listen, to notice, to create, to reflect, to remember, and to celebrate the light found in everyday life.</p>
            <p>At the heart of it all are a few ideas:</p>
            <p>Love is creation’s greatest idea.</p>
            <p>We are one.</p>
            <p>The closer we move toward the light, the more we begin to look like the light.</p>
            <p>All the Things Light is an invitation to experience those ideas—not only through words, but through music, conversation, gratitude, creativity, and connection.</p>
            <p>There is room here for joy.</p>
            <p>There is room here for reflection.</p>
            <p>There is room here for questions.</p>
            <p>There is room here to simply be.</p>
            <p>Come and Gather. ✨</p>
            HTML;
    }
}
