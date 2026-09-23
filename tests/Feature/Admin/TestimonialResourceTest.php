<?php

namespace Tests\Feature\Admin;

use App\Actions\Media\DeleteMediaAction;
use App\Exceptions\Media\MediaInUseException;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Resources\Testimonials\Pages\EditTestimonial;
use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Models\Media;
use App\Models\Role;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TestimonialResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_testimonials(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get('/admin/testimonials')->assertForbidden();
    }

    public function test_admin_can_list_testimonials(): void
    {
        $testimonial = Testimonial::query()->create([
            'name' => 'Jane Doe',
            'designation' => 'Listener',
            'message' => 'Beautiful music.',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListTestimonials::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$testimonial]);
    }

    public function test_admin_can_create_a_testimonial_with_an_avatar(): void
    {
        $admin = $this->admin();
        $media = $this->imageMedia($admin);

        Livewire::actingAs($admin)
            ->test(CreateTestimonial::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'designation' => 'Listener, Portland',
                'message' => 'Beautiful music.',
                'avatar_media_id' => $media->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $testimonial = Testimonial::query()->sole();
        $this->assertSame('Jane Doe', $testimonial->name);
        $this->assertSame('Listener, Portland', $testimonial->designation);
        $this->assertSame('Beautiful music.', $testimonial->message);
        $this->assertSame($media->id, $testimonial->avatar_media_id);
        $this->assertTrue($testimonial->is_enabled);
        $this->assertSame($admin->id, $testimonial->created_by);
    }

    public function test_name_and_message_are_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateTestimonial::class)
            ->fillForm(['name' => '', 'message' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'message' => 'required']);
    }

    public function test_admin_can_edit_a_testimonial(): void
    {
        $admin = $this->admin();
        $testimonial = Testimonial::query()->create([
            'name' => 'Jane Doe',
            'message' => 'Old message.',
        ]);

        Livewire::actingAs($admin)
            ->test(EditTestimonial::class, ['record' => $testimonial->getRouteKey()])
            ->fillForm(['message' => 'New message.', 'designation' => 'Founder'])
            ->call('save')
            ->assertHasNoFormErrors();

        $testimonial->refresh();
        $this->assertSame('New message.', $testimonial->message);
        $this->assertSame('Founder', $testimonial->designation);
        $this->assertSame($admin->id, $testimonial->updated_by);
    }

    public function test_media_used_as_a_testimonial_avatar_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $media = $this->imageMedia($admin);
        Testimonial::query()->create([
            'name' => 'Jane Doe',
            'message' => 'Beautiful music.',
            'avatar_media_id' => $media->id,
        ]);

        $this->expectException(MediaInUseException::class);

        app(DeleteMediaAction::class)->handle($media, $admin);
    }

    private function imageMedia(User $uploader): Media
    {
        Storage::fake('public');
        $path = 'media/images/avatar.jpg';
        Storage::disk('public')->put($path, 'fake-jpg-bytes');

        $media = new Media;
        $media->disk = 'public';
        $media->path = $path;
        $media->original_filename = 'avatar.jpg';
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
