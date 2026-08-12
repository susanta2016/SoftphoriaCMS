<?php

namespace Tests\Feature\Admin;

use App\Actions\Media\ReplaceMediaFileAction;
use App\Actions\Media\StoreUploadedMediaAction;
use App\Enums\MediaCategory;
use App\Exceptions\Media\MediaCategoryMismatchException;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Jobs\Media\GenerateImageVariantsJob;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ADMIN-005 review fix: Replace File on the Media Edit page
 * (ReplaceMediaFileAction / MediaResource::replaceFileAction()).
 */
class MediaReplaceFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacing_an_image_via_the_edit_page_action_stores_the_new_file_and_keeps_the_id(): void
    {
        Storage::fake('public');
        Queue::fake();

        $admin = $this->admin();
        $original = $this->storeImage($admin, 'public', 'media/images/old.jpg', 800, 600);
        $originalId = $original->id;
        $originalPublicId = $original->public_id;

        Livewire::actingAs($admin)
            ->test(EditMedia::class, ['record' => $original->getRouteKey()])
            ->callFormComponentAction('replaceFileActions', 'replaceFile', data: [
                'file' => UploadedFile::fake()->image('new.jpg', 1000, 800),
            ])
            ->assertHasNoFormComponentActionErrors();

        $original->refresh();

        $this->assertSame($originalId, $original->id);
        $this->assertSame($originalPublicId, $original->public_id);
        $this->assertSame($admin->id, $original->uploader_id);
        $this->assertSame($admin->id, $original->updated_by);
        Storage::disk('public')->assertExists($original->path);
        Storage::disk('public')->assertMissing('media/images/old.jpg');
    }

    public function test_replacing_a_document_updates_the_record_and_runs_no_image_processing(): void
    {
        Storage::fake('public');
        Queue::fake();

        $admin = $this->admin();
        $media = new Media;
        $media->disk = 'public';
        $media->path = 'media/documents/old.pdf';
        $media->original_filename = 'old.pdf';
        $media->mime_type = 'application/pdf';
        $media->size = 100;
        $media->visibility = 'public';
        $media->uploader_id = $admin->id;
        Storage::disk('public')->put($media->path, 'old-pdf-bytes');
        $media->save();

        $newPath = 'media/documents/new.pdf';
        Storage::disk('public')->put($newPath, 'new-pdf-bytes');

        app(ReplaceMediaFileAction::class)->handle($media, 'public', $newPath, $admin);

        $media->refresh();
        $this->assertSame('application/pdf', $media->mime_type);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing('media/documents/old.pdf');
        Queue::assertNotPushed(GenerateImageVariantsJob::class);
    }

    public function test_replacing_audio_updates_the_record_and_runs_no_image_processing(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeNonImage($admin, 'audio/mpeg', 'media/audio/old.mp3');

        $newPath = 'media/audio/new.mp3';
        Storage::disk('local')->put($newPath, 'new-mp3-bytes');

        app(ReplaceMediaFileAction::class)->handle($media, 'local', $newPath, $admin);

        $media->refresh();
        $this->assertSame(MediaCategory::Audio, $media->category());
        Storage::disk('local')->assertExists($newPath);
        Storage::disk('local')->assertMissing('media/audio/old.mp3');
        Queue::assertNotPushed(GenerateImageVariantsJob::class);
    }

    public function test_replacing_video_updates_the_record_and_runs_no_image_processing(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeNonImage($admin, 'video/mp4', 'media/video/old.mp4');

        $newPath = 'media/video/new.mp4';
        Storage::disk('local')->put($newPath, 'new-mp4-bytes');

        app(ReplaceMediaFileAction::class)->handle($media, 'local', $newPath, $admin);

        $media->refresh();
        $this->assertSame(MediaCategory::Video, $media->category());
        Storage::disk('local')->assertExists($newPath);
        Storage::disk('local')->assertMissing('media/video/old.mp4');
        Queue::assertNotPushed(GenerateImageVariantsJob::class);
    }

    public function test_replace_rejects_a_category_mismatch_and_leaves_the_old_file_untouched(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $image = $this->storeImage($admin, 'public', 'media/images/old.jpg', 800, 600);

        $audioPath = 'media/audio/sneaky.mp3';
        Storage::disk('local')->put($audioPath, 'mp3-bytes');

        $this->expectException(MediaCategoryMismatchException::class);

        try {
            app(ReplaceMediaFileAction::class)->handle($image, 'local', $audioPath, $admin);
        } finally {
            $image->refresh();
            // Nothing changed — mismatch is caught before any write.
            $this->assertSame('media/images/old.jpg', $image->path);
            $this->assertSame('public', $image->disk);
            Storage::disk('public')->assertExists('media/images/old.jpg');
        }
    }

    public function test_the_replace_file_action_rejects_a_category_mismatch_via_the_ui_with_a_notification_not_an_exception(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $image = $this->storeImage($admin, 'public', 'media/images/old.jpg', 800, 600);

        // The FileUpload field itself is constrained to the record's own
        // category's accepted types, so acceptedFileTypes() rejects a
        // mismatched upload as a normal form validation error.
        Livewire::actingAs($admin)
            ->test(EditMedia::class, ['record' => $image->getRouteKey()])
            ->callFormComponentAction('replaceFileActions', 'replaceFile', data: [
                'file' => UploadedFile::fake()->create('sneaky.mp3', 100, 'audio/mpeg'),
            ])
            ->assertHasFormComponentActionErrors();

        $image->refresh();
        $this->assertSame('media/images/old.jpg', $image->path);
    }

    public function test_image_upload_rejects_invalid_mime_type_on_replace(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $image = $this->storeImage($admin, 'public', 'media/images/old.jpg', 800, 600);

        Livewire::actingAs($admin)
            ->test(EditMedia::class, ['record' => $image->getRouteKey()])
            ->callFormComponentAction('replaceFileActions', 'replaceFile', data: [
                'file' => UploadedFile::fake()->create('icon.svg', 5, 'image/svg+xml'),
            ])
            ->assertHasFormComponentActionErrors();
    }

    public function test_replace_rejects_an_oversized_file(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $image = $this->storeImage($admin, 'public', 'media/images/old.jpg', 800, 600);

        Livewire::actingAs($admin)
            ->test(EditMedia::class, ['record' => $image->getRouteKey()])
            ->callFormComponentAction('replaceFileActions', 'replaceFile', data: [
                'file' => UploadedFile::fake()->create('huge.jpg', 6 * 1024, 'image/jpeg'),
            ])
            ->assertHasFormComponentActionErrors();
    }

    public function test_replacing_an_image_deletes_the_old_variants_and_the_old_original(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/old.jpg', 1600, 1200);
        (new GenerateImageVariantsJob($media->id))->handle();

        $oldVariantPaths = $media->variants()->pluck('path')->all();
        $this->assertNotEmpty($oldVariantPaths);
        foreach ($oldVariantPaths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $newPath = 'media/images/new.jpg';
        Storage::disk('local')->put($newPath, UploadedFile::fake()->image('new.jpg', 1000, 800)->get());

        app(ReplaceMediaFileAction::class)->handle($media, 'local', $newPath, $admin);

        Storage::disk('local')->assertMissing('media/images/old.jpg');
        foreach ($oldVariantPaths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        $this->assertSame(0, MediaVariant::query()->where('media_id', $media->id)->where('path', $oldVariantPaths[0])->count());
    }

    public function test_replacing_an_image_resets_variants_generated_at_and_dispatches_a_new_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/old.jpg', 1600, 1200);
        (new GenerateImageVariantsJob($media->id))->handle();
        $media->refresh();
        $this->assertNotNull($media->variants_generated_at);

        Queue::fake(); // reset the dispatch log from the manual job run above

        $newPath = 'media/images/new.jpg';
        Storage::disk('local')->put($newPath, UploadedFile::fake()->image('new.jpg', 1000, 800)->get());

        app(ReplaceMediaFileAction::class)->handle($media, 'local', $newPath, $admin);

        $media->refresh();
        $this->assertNull($media->variants_generated_at);
        Queue::assertPushed(GenerateImageVariantsJob::class, fn (GenerateImageVariantsJob $job): bool => $job->mediaId === $media->id);
    }

    public function test_replace_writes_a_media_replaced_audit_log_entry(): void
    {
        Storage::fake('public');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'public', 'media/images/old-name.jpg', 800, 600);

        $newPath = 'media/images/new-name.jpg';
        Storage::disk('public')->put($newPath, UploadedFile::fake()->image('new.jpg', 800, 600)->get());

        app(ReplaceMediaFileAction::class)->handle($media, 'public', $newPath, $admin);

        $media->refresh();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'media.replaced',
            'entity_type' => 'Media',
            'entity_id' => $media->id,
        ]);

        $log = AuditLog::query()->where('action', 'media.replaced')->latest('id')->firstOrFail();
        $this->assertSame('old-name.jpg', $log->metadata['old_filename']);
        $this->assertSame($media->original_filename, $log->metadata['new_filename']);
        $this->assertSame('image', $log->metadata['category']);
    }

    public function test_the_page_stops_showing_the_old_variants_immediately_after_replacing(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/old.jpg', 1600, 1200);
        (new GenerateImageVariantsJob($media->id))->handle();

        Queue::fake();

        // Same staleness risk as the regenerate action's test above —
        // mounting the page caches the `variants` relation on $record
        // before the replaceFile action mutates the same object.
        Livewire::actingAs($admin)
            ->test(EditMedia::class, ['record' => $media->getRouteKey()])
            ->assertSee('>Generated<', false)
            ->callFormComponentAction('replaceFileActions', 'replaceFile', data: [
                'file' => UploadedFile::fake()->image('new.jpg', 1000, 800),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->assertSee('Processing')
            ->assertSee('No variants.')
            ->assertDontSee('>Generated<', false);
    }

    public function test_non_admin_cannot_replace_a_file(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $nonAdmin = User::factory()->create(['status' => 'active']);
        $media = $this->storeImage($admin, 'public', 'media/images/old.jpg', 800, 600);

        $response = $this->actingAs($nonAdmin)->get("/admin/media/{$media->id}/edit");

        $response->assertForbidden();
    }

    private function storeImage(User $uploader, string $disk, string $path, int $width, int $height): Media
    {
        Storage::disk($disk)->put($path, UploadedFile::fake()->image(basename($path), $width, $height)->get());

        return app(StoreUploadedMediaAction::class)->handle($disk, $path, $uploader);
    }

    private function storeNonImage(User $uploader, string $mimeType, string $path): Media
    {
        Storage::disk('local')->put($path, 'placeholder-bytes');

        $media = new Media;
        $media->disk = 'local';
        $media->path = $path;
        $media->original_filename = basename($path);
        $media->mime_type = $mimeType;
        $media->size = Storage::disk('local')->size($path);
        $media->visibility = 'protected';
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
