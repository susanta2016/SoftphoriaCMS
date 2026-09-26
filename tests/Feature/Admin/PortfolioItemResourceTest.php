<?php

namespace Tests\Feature\Admin;

use App\Actions\Media\DeleteMediaAction;
use App\Exceptions\Media\MediaInUseException;
use App\Filament\Resources\PortfolioItems\Pages\CreatePortfolioItem;
use App\Filament\Resources\PortfolioItems\Pages\EditPortfolioItem;
use App\Filament\Resources\PortfolioItems\Pages\ListPortfolioItems;
use App\Models\Media;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PortfolioItemResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_the_portfolio(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get('/admin/portfolio')->assertForbidden();
    }

    public function test_admin_can_open_the_portfolio_pages(): void
    {
        $admin = $this->admin();
        $item = PortfolioItem::query()->create(['title' => 'Acme Shop']);

        $this->actingAs($admin)->get('/admin/portfolio')->assertOk()->assertSee('Acme Shop');
        $this->actingAs($admin)->get('/admin/portfolio/create')->assertOk();
        $this->actingAs($admin)->get("/admin/portfolio/{$item->id}/edit")->assertOk();
    }

    public function test_admin_can_list_and_filter_by_featured(): void
    {
        $featured = PortfolioItem::query()->create(['title' => 'Featured One', 'is_featured' => true]);
        $plain = PortfolioItem::query()->create(['title' => 'Plain One']);

        Livewire::actingAs($this->admin())
            ->test(ListPortfolioItems::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$featured, $plain])
            ->filterTable('is_featured', true)
            ->assertCanSeeTableRecords([$featured])
            ->assertCanNotSeeTableRecords([$plain]);
    }

    public function test_admin_can_create_a_featured_portfolio_item(): void
    {
        $admin = $this->admin();
        $media = $this->imageMedia($admin);

        Livewire::actingAs($admin)
            ->test(CreatePortfolioItem::class)
            ->fillForm([
                'title' => 'Acme Shop',
                'category' => 'E-Commerce',
                'summary' => 'A B2B storefront.',
                'technologies' => ['Laravel', 'AWS'],
                'link_url' => 'https://acme.example',
                'cover_media_id' => $media->id,
                'is_featured' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = PortfolioItem::query()->sole();
        $this->assertSame('Acme Shop', $item->title);
        $this->assertSame('E-Commerce', $item->category);
        $this->assertSame(['Laravel', 'AWS'], $item->technologies);
        $this->assertSame('https://acme.example', $item->link_url);
        $this->assertSame($media->id, $item->cover_media_id);
        $this->assertTrue($item->is_featured);
        $this->assertTrue($item->is_published);
        $this->assertSame($admin->id, $item->created_by);
    }

    public function test_new_items_are_not_featured_by_default(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePortfolioItem::class)
            ->fillForm(['title' => 'Acme Shop'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(PortfolioItem::query()->sole()->is_featured);
    }

    public function test_title_is_required_and_the_link_must_be_a_url_or_path(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePortfolioItem::class)
            ->fillForm(['title' => '', 'link_url' => 'javascript:alert(1)'])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required', 'link_url']);

        $this->assertSame(0, PortfolioItem::query()->count());
    }

    public function test_admin_can_edit_and_unfeature_an_item(): void
    {
        $admin = $this->admin();
        $item = PortfolioItem::query()->create(['title' => 'Acme Shop', 'is_featured' => true]);

        Livewire::actingAs($admin)
            ->test(EditPortfolioItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => 'Acme Store', 'is_featured' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $item->refresh();
        $this->assertSame('Acme Store', $item->title);
        $this->assertFalse($item->is_featured);
        $this->assertSame($admin->id, $item->updated_by);
    }

    public function test_featured_can_be_toggled_from_the_list(): void
    {
        $admin = $this->admin();
        $item = PortfolioItem::query()->create(['title' => 'Acme Shop']);

        Livewire::actingAs($admin)
            ->test(ListPortfolioItems::class)
            ->call('updateTableColumnState', 'is_featured', (string) $item->getKey(), true);

        $item->refresh();
        $this->assertTrue($item->is_featured);
        $this->assertSame($admin->id, $item->updated_by);
    }

    public function test_media_used_as_a_portfolio_cover_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $media = $this->imageMedia($admin);
        PortfolioItem::query()->create(['title' => 'Acme Shop', 'cover_media_id' => $media->id]);

        $this->expectException(MediaInUseException::class);

        app(DeleteMediaAction::class)->handle($media, $admin);
    }

    private function imageMedia(User $uploader): Media
    {
        Storage::fake('public');
        $path = 'media/images/cover.jpg';
        Storage::disk('public')->put($path, 'fake-jpg-bytes');

        $media = new Media;
        $media->disk = 'public';
        $media->path = $path;
        $media->original_filename = 'cover.jpg';
        $media->mime_type = 'image/jpeg';
        $media->size = 14;
        $media->visibility = 'public';
        $media->uploader_id = $uploader->id;
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
