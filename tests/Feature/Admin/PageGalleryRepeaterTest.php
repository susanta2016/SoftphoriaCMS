<?php

namespace Tests\Feature\Admin;

use App\Actions\Page\CreatePageAction;
use App\Enums\PageTemplate;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * WEB-101 item E — the Gallery section's admin field, upgraded from a bare
 * content_json.media_ids ID list to a structured Repeater (same pattern as
 * the FAQ repeater) of {media_id, title, description, url}. Covers saving
 * the new shape and hydrating a section saved under the old shape.
 */
class PageGalleryRepeaterTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_gallery_section_persists_the_structured_items(): void
    {
        $admin = $this->admin();
        $media = $this->media();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'Gallery Page', 'slug' => 'gallery-page', 'template' => PageTemplate::Standard->value,
        ], $admin);

        Livewire::actingAs($admin)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'sections' => [[
                    'section_type' => 'gallery',
                    'title' => 'Gallery',
                    'is_enabled' => true,
                    'content_json' => [
                        'gallery_items' => [[
                            'media_id' => $media->id,
                            'title' => 'First Piece',
                            'description' => 'A description.',
                            'url' => 'https://example.com/first',
                        ]],
                    ],
                ]],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $section = $page->fresh()->sections()->first();
        $this->assertSame('gallery', $section->section_type);
        $this->assertSame($media->id, $section->content_json['gallery_items'][0]['media_id']);
        $this->assertSame('First Piece', $section->content_json['gallery_items'][0]['title']);
        $this->assertSame('https://example.com/first', $section->content_json['gallery_items'][0]['url']);
    }

    public function test_editing_a_legacy_media_ids_gallery_section_hydrates_the_repeater(): void
    {
        $admin = $this->admin();
        $media = $this->media();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'Legacy Gallery Page', 'slug' => 'legacy-gallery-page', 'template' => PageTemplate::Standard->value,
            'sections' => [
                ['section_type' => 'gallery', 'is_enabled' => true, 'content_json' => ['media_ids' => [$media->id]]],
            ],
        ], $admin);

        $instance = Livewire::actingAs($admin)
            ->test(EditPage::class, ['record' => $page->getRouteKey()]);

        // The outer "sections" Repeater (and the nested "gallery_items"
        // Repeater it hydrates into) re-key their items by UUID once
        // mounted, so the item can't be addressed by a fixed numeric index
        // — read the actual (only) section/item back out instead.
        $sections = $instance->get('data.sections');
        $section = collect($sections)->sole();
        $galleryItem = collect($section['content_json']['gallery_items'])->sole();

        $this->assertSame($media->id, $galleryItem['media_id']);
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
