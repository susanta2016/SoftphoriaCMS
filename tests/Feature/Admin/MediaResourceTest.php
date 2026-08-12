<?php

namespace Tests\Feature\Admin;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Enums\MediaCategory;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Media\Pages\UploadMedia;
use App\Jobs\Media\GenerateImageVariantsJob;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class MediaResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_the_media_list(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/media');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_media_list(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListMedia::class)
            ->assertSuccessful();
    }

    public function test_uploading_an_image_preserves_the_original_untouched(): void
    {
        Storage::fake('public');
        Queue::fake();

        $upload = UploadedFile::fake()->image('photo.jpg', 1600, 1200);
        $originalBytes = file_get_contents($upload->getPathname());

        Livewire::actingAs($this->admin())
            ->test(UploadMedia::class)
            ->fillForm([
                'category' => MediaCategory::Image->value,
                'file' => $upload,
                'alt_text' => 'A test photo',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Filament's FileUpload stores under a generated filename (not the
        // client's original name), matching the existing avatar-upload
        // behavior — see ResolvesAvatarMedia — so look up by recency, not name.
        $media = Media::query()->latest('id')->firstOrFail();

        $this->assertSame('public', $media->disk);
        $this->assertSame('A test photo', $media->alt_text);
        Storage::disk('public')->assertExists($media->path);
        $this->assertSame($originalBytes, Storage::disk('public')->get($media->path));
    }

    public function test_uploading_a_qualifying_image_dispatches_variant_generation(): void
    {
        Storage::fake('public');
        Queue::fake();

        Livewire::actingAs($this->admin())
            ->test(UploadMedia::class)
            ->fillForm([
                'category' => MediaCategory::Image->value,
                'file' => UploadedFile::fake()->image('big.jpg', 1600, 1200),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $media = Media::query()->latest('id')->firstOrFail();

        Queue::assertPushed(GenerateImageVariantsJob::class, fn (GenerateImageVariantsJob $job): bool => $job->mediaId === $media->id);
    }

    public function test_the_job_generates_a_300x300_thumbnail_and_640_1280_responsive_widths_without_upscaling(): void
    {
        Storage::fake('local');
        Queue::fake(); // handle() dispatches a real job on sync-queue tests otherwise — this test invokes it once, manually.

        $admin = $this->admin();
        $path = 'media/images/big.jpg';
        Storage::disk('local')->put($path, UploadedFile::fake()->image('big.jpg', 1600, 1200)->get());

        $media = app(StoreUploadedMediaAction::class)->handle('local', $path, $admin);

        (new GenerateImageVariantsJob($media->id))->handle();

        $media->refresh();
        $this->assertNotNull($media->variants_generated_at);

        $thumbnails = $media->variants()->where('type', 'thumbnail')->get();
        $this->assertCount(2, $thumbnails); // webp + avif
        foreach ($thumbnails as $thumbnail) {
            $this->assertSame(300, $thumbnail->width);
            $this->assertSame(300, $thumbnail->height);
            Storage::disk('local')->assertExists($thumbnail->path);
        }

        $responsive640 = $media->variants()->where('type', 'responsive')->where('width', 640)->get();
        $responsive1280 = $media->variants()->where('type', 'responsive')->where('width', 1280)->get();
        $this->assertCount(2, $responsive640); // webp + avif
        $this->assertCount(2, $responsive1280);

        $this->assertSame(['avif', 'webp'], $media->variants()->where('type', 'responsive')->where('width', 640)->pluck('format')->sort()->values()->all());
    }

    public function test_responsive_variants_are_not_generated_above_the_original_width(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $path = 'media/images/small.jpg';
        // 400px wide: narrower than both 640 and 1280, so neither should be generated.
        Storage::disk('local')->put($path, UploadedFile::fake()->image('small.jpg', 400, 300)->get());

        $media = app(StoreUploadedMediaAction::class)->handle('local', $path, $admin);
        (new GenerateImageVariantsJob($media->id))->handle();

        $this->assertSame(0, $media->variants()->where('type', 'responsive')->count());
        // The thumbnail still generates (coverDown never upscales past the source either).
        $this->assertSame(2, $media->variants()->where('type', 'thumbnail')->count());
    }

    public function test_images_below_the_minimum_dimension_get_no_variants_at_all(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $path = 'media/images/tiny.jpg';
        Storage::disk('local')->put($path, UploadedFile::fake()->image('tiny.jpg', 10, 10)->get());

        app(StoreUploadedMediaAction::class)->handle('local', $path, $admin);

        Queue::assertNotPushed(GenerateImageVariantsJob::class);
    }

    public function test_animated_gifs_are_not_queued_for_variant_generation(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $path = 'media/images/animated.gif';
        Storage::disk('local')->put($path, $this->fakeAnimatedGif());

        app(StoreUploadedMediaAction::class)->handle('local', $path, $admin);

        Queue::assertNotPushed(GenerateImageVariantsJob::class);
    }

    public function test_the_animated_gif_byte_heuristic_correctly_counts_graphic_control_extensions(): void
    {
        $action = new StoreUploadedMediaAction;
        $method = new ReflectionMethod($action, 'isAnimatedGif');
        $method->setAccessible(true);

        $single = tempnam(sys_get_temp_dir(), 'gif');
        file_put_contents($single, "GIF89a\x21\xF9\x04".str_repeat('x', 10));
        $this->assertFalse($method->invoke($action, $single));

        $animated = tempnam(sys_get_temp_dir(), 'gif');
        file_put_contents($animated, "GIF89a\x21\xF9\x04".str_repeat('x', 5)."\x21\xF9\x04".str_repeat('y', 5));
        $this->assertTrue($method->invoke($action, $animated));

        @unlink($single);
        @unlink($animated);
    }

    public function test_image_upload_rejects_a_file_over_the_category_size_limit(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(UploadMedia::class)
            ->fillForm([
                'category' => MediaCategory::Image->value,
                'file' => UploadedFile::fake()->create('huge.jpg', 6 * 1024, 'image/jpeg'), // 6MB > 5MB image limit
            ])
            ->call('create')
            ->assertHasFormErrors(['file']);
    }

    public function test_image_upload_rejects_svg(): void
    {
        Livewire::actingAs($this->admin())
            ->test(UploadMedia::class)
            ->fillForm([
                'category' => MediaCategory::Image->value,
                'file' => UploadedFile::fake()->create('icon.svg', 5, 'image/svg+xml'),
            ])
            ->call('create')
            ->assertHasFormErrors(['file']);
    }

    public function test_video_category_accepts_uploads_up_to_the_200mb_limit(): void
    {
        $this->assertSame(200 * 1024, config('media.categories.video.max_size'));
    }

    public function test_admin_can_upload_audio_and_it_is_categorized_and_stored(): void
    {
        Storage::fake('local');
        Queue::fake();

        Livewire::actingAs($this->admin())
            ->test(UploadMedia::class)
            ->fillForm([
                'category' => MediaCategory::Audio->value,
                'file' => UploadedFile::fake()->create('song.mp3', 500, 'audio/mpeg'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $media = Media::query()->latest('id')->firstOrFail();

        $this->assertSame('local', $media->disk);
        $this->assertSame('protected', $media->visibility);
        Storage::disk('local')->assertExists($media->path);
        // Never processed as an image, regardless of what finfo sniffs from fake content.
        Queue::assertNotPushed(GenerateImageVariantsJob::class);
    }

    public function test_admin_can_play_back_protected_audio_via_the_stream_route(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $path = 'media/audio/song.mp3';
        Storage::disk('local')->put($path, 'fake-mp3-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = 'song.mp3';
        $media->mime_type = 'audio/mpeg';
        $media->size = 14;
        $media->visibility = 'protected';
        $media->uploader_id = $admin->id;
        $media->save();

        $response = $this->actingAs($admin)->get(route('media.stream', $media));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/mpeg');
    }

    public function test_guest_cannot_stream_protected_media(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $path = 'media/video/clip.mp4';
        Storage::disk('local')->put($path, 'fake-mp4-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = 'clip.mp4';
        $media->mime_type = 'video/mp4';
        $media->size = 14;
        $media->visibility = 'protected';
        $media->uploader_id = $admin->id;
        $media->save();

        $response = $this->get(route('media.stream', $media));

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_stream_protected_media(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $nonAdmin = User::factory()->create(['status' => 'active']);

        $path = 'media/video/clip.mp4';
        Storage::disk('local')->put($path, 'fake-mp4-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = 'clip.mp4';
        $media->mime_type = 'video/mp4';
        $media->size = 14;
        $media->visibility = 'protected';
        $media->uploader_id = $admin->id;
        $media->save();

        $response = $this->actingAs($nonAdmin)->get(route('media.stream', $media));

        $response->assertForbidden();
    }

    public function test_stream_route_refuses_non_audio_video_media(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $path = 'media/documents/file.pdf';
        Storage::disk('public')->put($path, 'fake-pdf-bytes');

        $media = new Media;
        $media->disk = 'public';
        $media->path = $path;
        $media->original_filename = 'file.pdf';
        $media->mime_type = 'application/pdf';
        $media->size = 14;
        $media->visibility = 'public';
        $media->uploader_id = $admin->id;
        $media->save();

        $response = $this->actingAs($admin)->get(route('media.stream', $media));

        $response->assertNotFound();
    }

    public function test_media_category_is_derived_from_mime_type_not_stored(): void
    {
        $this->assertFalse(Schema::hasColumn('media', 'category'));

        $media = new Media(['mime_type' => 'image/png']);
        $this->assertSame(MediaCategory::Image, $media->category());

        $media = new Media(['mime_type' => 'audio/mpeg']);
        $this->assertSame(MediaCategory::Audio, $media->category());

        $media = new Media(['mime_type' => 'video/mp4']);
        $this->assertSame(MediaCategory::Video, $media->category());

        $media = new Media(['mime_type' => 'application/pdf']);
        $this->assertSame(MediaCategory::Document, $media->category());

        $media = new Media(['mime_type' => 'application/zip']);
        $this->assertNull($media->category());
    }

    public function test_deleting_a_media_row_is_a_soft_delete(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $path = 'media/images/photo.jpg';
        Storage::disk('public')->put($path, 'fake-bytes');

        $media = app(StoreUploadedMediaAction::class)->handle('public', $path, $admin);

        Livewire::actingAs($admin)
            ->test(ListMedia::class)
            ->callTableAction('delete', $media)
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    public function test_media_variants_table_cascades_on_media_deletion(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();

        $path = 'media/images/big.jpg';
        Storage::disk('local')->put($path, UploadedFile::fake()->image('big.jpg', 1600, 1200)->get());

        $media = app(StoreUploadedMediaAction::class)->handle('local', $path, $admin);
        (new GenerateImageVariantsJob($media->id))->handle();

        $this->assertGreaterThan(0, MediaVariant::query()->where('media_id', $media->id)->count());

        $media->forceDelete();

        $this->assertSame(0, MediaVariant::query()->where('media_id', $media->id)->count());
    }

    private function fakeAnimatedGif(): string
    {
        $image = imagecreatetruecolor(20, 20);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        ob_start();
        imagegif($image);
        $single = ob_get_clean();
        imagedestroy($image);

        $gce = "\x21\xF9\x04\x00\x00\x00\x00\x00";

        // Splice two synthetic Graphic Control Extension blocks in after the
        // fixed header + global color table (byte 19 for a 2-color GCT) —
        // getimagesize() only reads the first 10 bytes for dimensions, so
        // this stays readable while giving isAnimatedGif() two markers.
        return substr($single, 0, 19).$gce.$gce.substr($single, 19);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
