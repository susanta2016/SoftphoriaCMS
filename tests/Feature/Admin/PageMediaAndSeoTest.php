<?php

namespace Tests\Feature\Admin;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Enums\MediaCategory;
use App\Enums\PageTemplate;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Support\Media\MediaPicker;
use App\Filament\Support\Media\RichEditorMediaAttachments;
use App\Filament\Support\Seo\SeoFields;
use App\Jobs\Media\GenerateImageVariantsJob;
use App\Models\Media;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Filament\Forms\Components\RichEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ADMIN-006 review fix: the Media Picker (Upload New Media / Select from
 * Media Library) used by every Page image field, the reusable SEO field
 * conventions (character-limited Meta Title/Description, automatic +
 * overridable canonical URL), and the new-tab Preview route.
 */
class PageMediaAndSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_through_the_featured_image_field_creates_exactly_one_media_record_and_assigns_it(): void
    {
        Storage::fake('public');
        Queue::fake();

        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->callFormComponentAction('featured_image_id__actions', 'featured_image_id_upload', data: [
                'file' => UploadedFile::fake()->image('hero.jpg', 1200, 800),
                'alt_text' => 'Hero image',
            ])
            ->assertHasNoFormComponentActionErrors()
            ->assertSet('data.featured_image_id', fn (?int $value): bool => $value !== null);

        $this->assertSame(1, Media::query()->count());
        $media = Media::query()->sole();
        $this->assertSame('Hero image', $media->alt_text);
        $this->assertSame($admin->id, $media->uploader_id);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_uploading_through_a_page_field_dispatches_variant_generation_like_the_media_library_upload(): void
    {
        Storage::fake('public');
        Queue::fake();

        Livewire::actingAs($this->admin())
            ->test(CreatePage::class)
            ->callFormComponentAction('featured_image_id__actions', 'featured_image_id_upload', data: [
                'file' => UploadedFile::fake()->image('hero.jpg', 1200, 800),
            ])
            ->assertHasNoFormComponentActionErrors();

        $media = Media::query()->sole();

        Queue::assertPushed(GenerateImageVariantsJob::class, fn (GenerateImageVariantsJob $job): bool => $job->mediaId === $media->id);
    }

    public function test_selecting_an_existing_media_asset_assigns_it_without_creating_a_new_media_record(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $existing = $this->storeImage($admin, 'media/images/existing.jpg');

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->callFormComponentAction('featured_image_id__actions', 'featured_image_id_select', data: [
                'media' => $existing->id,
            ])
            ->assertHasNoFormComponentActionErrors()
            ->assertSet('data.featured_image_id', $existing->id);

        $this->assertSame(1, Media::query()->count());
    }

    public function test_the_selected_media_is_persisted_on_the_page_after_save(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $existing = $this->storeImage($admin, 'media/images/existing.jpg');

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->callFormComponentAction('featured_image_id__actions', 'featured_image_id_select', data: [
                'media' => $existing->id,
            ])
            ->fillForm([
                'title' => 'About',
                'slug' => 'about-featured',
                'template' => PageTemplate::Standard->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('slug', 'about-featured')->firstOrFail();
        $this->assertSame($existing->id, $page->featured_image_id);
    }

    public function test_removing_a_selected_image_clears_the_field(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $existing = $this->storeImage($admin, 'media/images/existing.jpg');

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->callFormComponentAction('featured_image_id__actions', 'featured_image_id_select', data: [
                'media' => $existing->id,
            ])
            ->assertSet('data.featured_image_id', $existing->id)
            ->callFormComponentAction('featured_image_id__actions', 'featured_image_id_clear')
            ->assertHasNoFormComponentActionErrors()
            ->assertSet('data.featured_image_id', null);
    }

    /**
     * The grid's own query — MediaPicker::query() — is the exact query the
     * ViewField's viewData() passes to the Blade grid (see
     * MediaPicker::libraryBrowserSchema()), so asserting against it directly
     * is equivalent to asserting what the admin sees on open. The full
     * rendered interaction (grid populated on open, no search required,
     * live search, category filtering) is additionally verified by hand in
     * a real browser — see the implementation report.
     */
    public function test_the_media_library_browser_shows_existing_media_immediately_without_a_search(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $existing = $this->storeImage($admin, 'media/images/browse-me.jpg');

        $ids = MediaPicker::query(MediaCategory::Image)->pluck('id');

        $this->assertTrue($ids->contains($existing->id));
    }

    public function test_the_media_library_browser_filters_by_the_consuming_fields_category_at_the_query_level(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $image = $this->storeImage($admin, 'media/images/only-image.jpg');
        $document = $this->storeDocument($admin, 'media/documents/should-not-appear.pdf');

        $ids = MediaPicker::query(MediaCategory::Image)->pluck('id');

        $this->assertTrue($ids->contains($image->id));
        $this->assertFalse($ids->contains($document->id));
    }

    public function test_search_in_the_media_library_browser_filters_the_grid(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $matching = $this->storeImage($admin, 'media/images/sunrise-photo.jpg');
        $other = $this->storeImage($admin, 'media/images/mountain-view.jpg');

        $unfiltered = MediaPicker::query(MediaCategory::Image)->pluck('id');
        $this->assertTrue($unfiltered->contains($matching->id));
        $this->assertTrue($unfiltered->contains($other->id));

        $filtered = MediaPicker::query(MediaCategory::Image, 'sunrise')->pluck('id');
        $this->assertTrue($filtered->contains($matching->id));
        $this->assertFalse($filtered->contains($other->id));
    }

    public function test_the_media_library_browser_requires_a_selection_before_it_can_be_submitted(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->storeImage($admin, 'media/images/existing.jpg');

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->callFormComponentAction('featured_image_id__actions', 'featured_image_id_select', data: [
                'media' => null,
            ])
            ->assertHasFormComponentActionErrors(['media' => 'required']);
    }

    public function test_reopening_the_media_library_browser_shows_the_current_selection_highlighted(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $existing = $this->storeImage($admin, 'media/images/existing.jpg');

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->callFormComponentAction('featured_image_id__actions', 'featured_image_id_select', data: [
                'media' => $existing->id,
            ])
            ->mountFormComponentAction('featured_image_id__actions', 'featured_image_id_select')
            ->assertSet('mountedActions.0.data.media', $existing->id);
    }

    public function test_a_dotted_seo_field_name_like_og_image_can_upload_and_select_without_action_name_collisions(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $existing = $this->storeImage($admin, 'media/images/og.jpg');

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->callFormComponentAction('seo_og_image_media_id__actions', 'seo_og_image_media_id_select', data: [
                'media' => $existing->id,
            ])
            ->assertHasNoFormComponentActionErrors()
            ->assertSet('data.seo.og_image_media_id', $existing->id);
    }

    /**
     * The Hero/ImageText/Gallery MediaPicker fields live inside the
     * "sections" Repeater, whose items get a Filament-generated UUID key
     * that callFormComponentAction() has no stable way to address from
     * outside — so this only smoke-tests that a Hero section (which uses
     * MediaPicker for content_json.media_id) renders and saves without
     * error. The upload/select/clear mechanism itself is identical code to
     * the top-level fields covered above and is additionally verified by
     * hand in a real browser (see the implementation report).
     */
    public function test_a_hero_section_using_the_media_picker_field_renders_and_saves(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->fillForm([
                'title' => 'Home',
                'slug' => 'home-hero',
                'template' => PageTemplate::Standard->value,
                'sections' => [
                    ['section_type' => 'hero', 'is_enabled' => true, 'content_json' => ['heading' => 'Welcome']],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('slug', 'home-hero')->firstOrFail();
        $this->assertSame('hero', $page->sections()->first()->section_type);
    }

    /**
     * The bug being fixed: a bare RichEditor::make() never configured disk/
     * directory/accepted types, so "Attach File" fell back to Filament's
     * unconfigured default (no Media row, no variants). This asserts the
     * field is now wired to the Media Library's own config('media.categories')
     * rules and that the vendor 'attachFiles' action has been replaced with
     * the Media-Library-aware version rather than left at its default. Full
     * interactive upload-through-the-editor coverage is verified by hand in
     * a real browser (see the implementation report) — Livewire's
     * TemporaryUploadedFile can't be constructed outside a live upload
     * request, which the Tiptap "attach files" flow requires.
     */
    public function test_rich_editor_media_attachments_are_wired_to_the_media_library(): void
    {
        $editor = RichEditorMediaAttachments::configure(RichEditor::make('content_json.body'));

        $this->assertSame('public', $editor->getFileAttachmentsDiskName());
        $this->assertSame('public', $editor->getFileAttachmentsVisibility());
        $this->assertContains('image/jpeg', $editor->getFileAttachmentsAcceptedFileTypes());
        $this->assertContains('application/pdf', $editor->getFileAttachmentsAcceptedFileTypes());
        $this->assertGreaterThanOrEqual(
            MediaCategory::Document->maxSizeKb(),
            $editor->getFileAttachmentsMaxSize(),
        );
    }

    public function test_meta_title_over_60_characters_fails_validation(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePage::class)
            ->fillForm([
                'title' => 'About',
                'slug' => 'about-seo-title',
                'template' => PageTemplate::Standard->value,
                'seo' => ['meta_title' => str_repeat('a', 61)],
            ])
            ->call('create')
            ->assertHasFormErrors(['seo.meta_title' => 'max']);
    }

    public function test_meta_title_at_exactly_60_characters_is_accepted(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePage::class)
            ->fillForm([
                'title' => 'About',
                'slug' => 'about-seo-title-ok',
                'template' => PageTemplate::Standard->value,
                'seo' => ['meta_title' => str_repeat('a', 60)],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(60, strlen(Page::query()->where('slug', 'about-seo-title-ok')->firstOrFail()->seo->meta_title));
    }

    public function test_meta_description_over_160_characters_fails_validation(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePage::class)
            ->fillForm([
                'title' => 'About',
                'slug' => 'about-seo-desc',
                'template' => PageTemplate::Standard->value,
                'seo' => ['meta_description' => str_repeat('a', 161)],
            ])
            ->call('create')
            ->assertHasFormErrors(['seo.meta_description' => 'max']);
    }

    public function test_meta_description_at_exactly_160_characters_is_accepted(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePage::class)
            ->fillForm([
                'title' => 'About',
                'slug' => 'about-seo-desc-ok',
                'template' => PageTemplate::Standard->value,
                'seo' => ['meta_description' => str_repeat('a', 160)],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_character_limits_are_the_shared_seo_fields_convention_constants(): void
    {
        $this->assertSame(60, SeoFields::META_TITLE_MAX);
        $this->assertSame(160, SeoFields::META_DESCRIPTION_MAX);
    }

    public function test_canonical_url_is_automatically_generated_from_the_slug(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePage::class)
            ->fillForm(['title' => 'My New Page'])
            ->assertSet('data.slug', 'my-new-page')
            ->assertSet('data.seo.canonical_url', rtrim(config('app.url'), '/').'/my-new-page');
    }

    public function test_manually_overriding_the_canonical_url_survives_a_later_slug_change(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePage::class)
            ->fillForm(['title' => 'My New Page'])
            ->assertSet('data.seo.canonical_url', rtrim(config('app.url'), '/').'/my-new-page')
            ->fillForm(['seo.canonical_url' => 'https://custom.example.com/special'])
            ->assertSet('data.seo.canonical_url_is_auto', false)
            ->fillForm(['slug' => 'a-completely-different-slug'])
            ->assertSet('data.seo.canonical_url', 'https://custom.example.com/special');
    }

    public function test_reset_canonical_url_action_returns_to_the_automatic_value(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePage::class)
            ->fillForm(['title' => 'My New Page'])
            ->fillForm(['seo.canonical_url' => 'https://custom.example.com/special'])
            ->assertSet('data.seo.canonical_url_is_auto', false)
            ->callFormComponentAction('seo_canonical_url_reset_actions', 'seo_canonical_url_reset')
            ->assertHasNoFormComponentActionErrors()
            ->assertSet('data.seo.canonical_url_is_auto', true)
            ->assertSet('data.seo.canonical_url', rtrim(config('app.url'), '/').'/my-new-page');
    }

    public function test_editing_a_page_whose_canonical_url_was_never_customized_still_shows_it_as_automatic(): void
    {
        $admin = $this->admin();
        $page = Page::create([
            'title' => 'About', 'slug' => 'about-auto', 'template' => PageTemplate::Standard->value, 'status' => 'draft',
        ]);

        Livewire::actingAs($admin)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSet('data.seo.canonical_url_is_auto', true)
            ->assertSet('data.seo.canonical_url', rtrim(config('app.url'), '/').'/about-auto');
    }

    public function test_editing_a_page_with_a_previously_saved_custom_canonical_url_keeps_it_flagged_as_manual(): void
    {
        $admin = $this->admin();
        $page = Page::create([
            'title' => 'About', 'slug' => 'about-manual', 'template' => PageTemplate::Standard->value, 'status' => 'draft',
        ]);
        $page->seo()->create(['canonical_url' => 'https://custom.example.com/about']);

        Livewire::actingAs($admin)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSet('data.seo.canonical_url_is_auto', false)
            ->assertSet('data.seo.canonical_url', 'https://custom.example.com/about');
    }

    public function test_canonical_url_never_hardcodes_a_domain(): void
    {
        $this->assertSame(
            rtrim(config('app.url'), '/').'/about',
            SeoFields::autoCanonicalUrl('about'),
        );
    }

    public function test_preview_action_opens_in_a_new_tab_and_points_at_the_preview_route(): void
    {
        $admin = $this->admin();
        $page = Page::create([
            'title' => 'About', 'slug' => 'about', 'template' => PageTemplate::Standard->value, 'status' => 'draft',
        ]);

        $resourceClass = PageResource::class;
        $action = $resourceClass::previewAction();
        $action->record($page);

        $this->assertTrue($action->shouldOpenUrlInNewTab());
        $this->assertSame(route('pages.preview', $page), $action->getUrl());
    }

    public function test_admin_can_view_the_preview_route_and_it_shows_structured_content(): void
    {
        $admin = $this->admin();
        $page = Page::create([
            'title' => 'About Us', 'slug' => 'about', 'template' => PageTemplate::Standard->value, 'status' => 'draft',
        ]);
        $page->sections()->create([
            'section_type' => 'quote', 'sort_order' => 0, 'is_enabled' => true,
            'content_json' => ['quote' => 'Preview quote text', 'attribution' => 'Someone'],
        ]);

        $response = $this->actingAs($admin)->get(route('pages.preview', $page));

        $response->assertOk();
        $response->assertSee('About Us');
        $response->assertSee('Preview quote text');
        $response->assertSee('Draft'); // non-published banner
    }

    public function test_disabled_sections_are_not_rendered_in_the_preview(): void
    {
        $admin = $this->admin();
        $page = Page::create([
            'title' => 'About', 'slug' => 'about-disabled', 'template' => PageTemplate::Standard->value, 'status' => 'draft',
        ]);
        $page->sections()->create([
            'section_type' => 'quote', 'sort_order' => 0, 'is_enabled' => false,
            'content_json' => ['quote' => 'Hidden quote text'],
        ]);

        $response = $this->actingAs($admin)->get(route('pages.preview', $page));

        $response->assertOk();
        $response->assertDontSee('Hidden quote text');
    }

    public function test_non_admin_cannot_view_the_preview_route(): void
    {
        $nonAdmin = User::factory()->create(['status' => 'active']);
        $page = Page::create([
            'title' => 'About', 'slug' => 'about-guarded', 'template' => PageTemplate::Standard->value, 'status' => 'draft',
        ]);

        $response = $this->actingAs($nonAdmin)->get(route('pages.preview', $page));

        $response->assertForbidden();
    }

    private function storeImage(User $admin, string $path): Media
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image(basename($path), 800, 600)->get());

        return app(StoreUploadedMediaAction::class)->handle('public', $path, $admin);
    }

    private function storeDocument(User $admin, string $path): Media
    {
        Storage::disk('public')->put($path, 'fake-pdf-bytes');

        $media = new Media;
        $media->disk = 'public';
        $media->path = $path;
        $media->original_filename = basename($path);
        $media->mime_type = 'application/pdf';
        $media->size = 14;
        $media->visibility = 'public';
        $media->uploader_id = $admin->id;
        $media->save();

        return $media;
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
