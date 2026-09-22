<?php

namespace Tests\Feature\Public;

use App\Actions\Page\CreatePageAction;
use App\Enums\PageTemplate;
use App\Models\Media;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WEB-101 — the generic public page renderer (resources/views/pages/show.blade.php),
 * rebuilt on the Softphoria design system. Covers every currently-supported
 * section type (still working exactly as before), the new gallery
 * structured-content shape alongside the legacy shape it must keep
 * rendering, the header's auth-state-aware links (kept from AUTH-002), and
 * unknown/unpublished page 404 behavior.
 */
class PageRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_page_renders_its_title_summary_and_featured_image(): void
    {
        $image = $this->media(['alt_text' => 'A featured photo']);

        $page = $this->publishedPage([
            'title' => 'About Softphoria',
            'slug' => 'about-softphoria',
            'summary' => 'What we do and why.',
            'featured_image_id' => $image->id,
        ]);

        $response = $this->get('/'.$page->slug);

        $response->assertOk();
        $response->assertSee('About Softphoria');
        $response->assertSee('What we do and why.');
        $response->assertSee('A featured photo', false);
    }

    public function test_every_currently_supported_section_type_still_renders(): void
    {
        $image = $this->media();

        $page = $this->publishedPage([
            'title' => 'Sections Showcase',
            'slug' => 'sections-showcase',
            'sections' => [
                ['section_type' => 'hero', 'is_enabled' => true, 'content_json' => [
                    'heading' => 'Hero Heading', 'subheading' => 'Hero Subheading',
                    'cta_label' => 'Primary CTA', 'cta_url' => '/register',
                ]],
                ['section_type' => 'rich_text', 'is_enabled' => true, 'content_json' => [
                    'body' => '<p>Some <strong>rich</strong> text.</p>',
                ]],
                ['section_type' => 'image_text', 'is_enabled' => true, 'content_json' => [
                    'media_id' => $image->id, 'text' => 'Image and text block',
                ]],
                ['section_type' => 'faq', 'is_enabled' => true, 'content_json' => [
                    'items' => [['question' => 'Is this a question?', 'answer' => 'Yes, an answer.']],
                ]],
                ['section_type' => 'quote', 'is_enabled' => true, 'content_json' => [
                    'quote' => 'A memorable quote.', 'attribution' => 'Someone Notable',
                ]],
                ['section_type' => 'cta', 'is_enabled' => true, 'content_json' => [
                    'heading' => 'Ready to start?', 'cta_label' => 'Go', 'cta_url' => '/register',
                ]],
            ],
        ]);

        $response = $this->get('/'.$page->slug);

        $response->assertOk();
        $response->assertSee('Hero Heading');
        $response->assertSee('Hero Subheading');
        $response->assertSee('Primary CTA');
        $response->assertSee('Some', false);
        $response->assertSee('rich', false);
        $response->assertSee('Image and text block');
        $response->assertSee('Is this a question?');
        $response->assertSee('Yes, an answer.');
        $response->assertSee('A memorable quote.');
        $response->assertSee('Someone Notable');
        $response->assertSee('Ready to start?');
    }

    public function test_a_disabled_section_is_not_rendered(): void
    {
        $page = $this->publishedPage([
            'title' => 'Disabled Section Page',
            'slug' => 'disabled-section-page',
            'sections' => [
                ['section_type' => 'quote', 'is_enabled' => false, 'content_json' => ['quote' => 'Hidden quote.']],
            ],
        ]);

        $response = $this->get('/'.$page->slug);

        $response->assertOk();
        $response->assertDontSee('Hidden quote.');
    }

    public function test_gallery_renders_the_legacy_flat_media_ids_shape(): void
    {
        $media = $this->media(['alt_text' => 'Legacy gallery photo']);

        $page = $this->publishedPage([
            'title' => 'Legacy Gallery',
            'slug' => 'legacy-gallery',
            'sections' => [
                ['section_type' => 'gallery', 'is_enabled' => true, 'content_json' => ['media_ids' => [$media->id]]],
            ],
        ]);

        $response = $this->get('/'.$page->slug);

        $response->assertOk();
        $response->assertSee('Legacy gallery photo', false);
    }

    public function test_gallery_renders_the_structured_gallery_items_shape(): void
    {
        $media = $this->media();

        $page = $this->publishedPage([
            'title' => 'Structured Gallery',
            'slug' => 'structured-gallery',
            'sections' => [
                ['section_type' => 'gallery', 'is_enabled' => true, 'content_json' => [
                    'gallery_items' => [[
                        'media_id' => $media->id,
                        'title' => 'A Portfolio Piece',
                        'description' => 'Describing the piece.',
                        'url' => 'https://example.com/piece',
                    ]],
                ]],
            ],
        ]);

        $response = $this->get('/'.$page->slug);

        $response->assertOk();
        $response->assertSee('A Portfolio Piece');
        $response->assertSee('Describing the piece.');
        $response->assertSee('https://example.com/piece', false);
    }

    public function test_guest_sees_login_and_register_links_on_a_public_page(): void
    {
        $page = $this->publishedPage(['title' => 'Guest Header', 'slug' => 'guest-header']);

        $response = $this->get('/'.$page->slug);

        $response->assertOk();
        $response->assertSee(route('login'), false);
        $response->assertSee(route('register'), false);
    }

    public function test_authenticated_user_sees_profile_and_logout_instead(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $page = $this->publishedPage(['title' => 'Auth Header', 'slug' => 'auth-header']);

        $response = $this->actingAs($user)->get('/'.$page->slug);

        $response->assertOk();
        $response->assertSee(route('account.profile.edit'), false);
        $response->assertSee(route('logout'), false);
        $response->assertDontSee(route('register'), false);
    }

    public function test_an_unknown_slug_with_no_redirect_returns_404(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();
    }

    public function test_an_unpublished_page_returns_404_to_the_public(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'Draft Page', 'slug' => 'draft-page', 'template' => PageTemplate::Standard->value,
        ], $admin);

        $this->assertSame('draft', $page->status->value);

        $response = $this->get('/'.$page->slug);

        $response->assertNotFound();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedPage(array $overrides): Page
    {
        $admin = $this->admin();

        $page = app(CreatePageAction::class)->handle(array_merge([
            'title' => 'Untitled',
            'slug' => 'untitled',
            'template' => PageTemplate::Standard->value,
        ], $overrides), $admin);

        $page->update(['status' => 'published']);

        return $page->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function media(array $overrides = []): Media
    {
        return Media::query()->create(array_merge([
            'disk' => 'public',
            'path' => 'media/test-'.uniqid().'.jpg',
            'original_filename' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'visibility' => 'public',
        ], $overrides));
    }
}
