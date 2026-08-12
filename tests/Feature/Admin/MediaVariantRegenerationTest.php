<?php

namespace Tests\Feature\Admin;

use App\Actions\Media\RegenerateMediaVariantsAction;
use App\Actions\Media\StoreUploadedMediaAction;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Jobs\Media\GenerateImageVariantsJob;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ADMIN-005 review fix: Responsive Variants status display + grouped
 * variant listing + Regenerate Variants action on the merged Media
 * Edit/Details page.
 */
class MediaVariantRegenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_shows_processing_when_no_variants_have_been_generated_or_failed(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/pending.jpg', 1600, 1200);

        Livewire::actingAs($admin)
            ->test(EditMedia::class, ['record' => $media->getRouteKey()])
            ->assertSee('Processing')
            ->assertDontSee('>Generated<', false)
            ->assertSee('No variants.');
    }

    public function test_status_shows_generated_with_grouped_variants_after_the_job_runs(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/big.jpg', 1600, 1200);
        (new GenerateImageVariantsJob($media->id))->handle();

        Livewire::actingAs($admin)
            ->test(EditMedia::class, ['record' => $media->getRouteKey()])
            ->assertSee('>Generated<', false)
            ->assertSee('Thumbnail (300')
            ->assertSee('Responsive Images (640px)')
            ->assertSee('Responsive Images (1280px)')
            ->assertSee('webp')
            ->assertSee('avif')
            ->assertSee('300')
            ->assertSee('640')
            ->assertSee('1280');
    }

    public function test_status_shows_failed_when_variants_failed_at_is_set(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/broken.jpg', 1600, 1200);
        $media->variants_failed_at = now();
        $media->save();

        Livewire::actingAs($admin)
            ->test(EditMedia::class, ['record' => $media->getRouteKey()])
            ->assertSee('Failed')
            ->assertSee('last attempt');
    }

    public function test_the_generate_job_failed_hook_sets_variants_failed_at(): void
    {
        Storage::fake('local');
        Queue::fake(); // otherwise the sync-queue test driver runs the job for real during setup

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/big.jpg', 1600, 1200);

        (new GenerateImageVariantsJob($media->id))->failed(new Exception('simulated failure'));

        $media->refresh();
        $this->assertNotNull($media->variants_failed_at);
        $this->assertNull($media->variants_generated_at);
    }

    public function test_a_successful_run_clears_a_previous_failure(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/big.jpg', 1600, 1200);

        (new GenerateImageVariantsJob($media->id))->failed(new Exception('simulated failure'));
        $media->refresh();
        $this->assertNotNull($media->variants_failed_at);

        (new GenerateImageVariantsJob($media->id))->handle();
        $media->refresh();

        $this->assertNull($media->variants_failed_at);
        $this->assertNotNull($media->variants_generated_at);
    }

    public function test_responsive_variants_section_is_hidden_for_non_image_media(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $media = new Media;
        $media->disk = 'public';
        $media->path = 'media/documents/report.pdf';
        $media->original_filename = 'report.pdf';
        $media->mime_type = 'application/pdf';
        $media->size = 100;
        $media->visibility = 'public';
        $media->uploader_id = $admin->id;
        Storage::disk('public')->put($media->path, 'pdf-bytes');
        $media->save();

        Livewire::actingAs($admin)
            ->test(EditMedia::class, ['record' => $media->getRouteKey()])
            ->assertDontSee('Responsive Variants')
            ->assertDontSee('Regenerate Variants');
    }

    public function test_regenerate_deletes_old_variants_keeps_the_original_and_dispatches_a_new_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/big.jpg', 1600, 1200);
        (new GenerateImageVariantsJob($media->id))->handle();

        $originalBytes = Storage::disk('local')->get($media->path);
        $oldVariantPaths = $media->variants()->pluck('path')->all();
        $this->assertNotEmpty($oldVariantPaths);

        Queue::fake(); // reset dispatch log from the manual run above

        $dispatched = app(RegenerateMediaVariantsAction::class)->handle($media, $admin);

        $this->assertTrue($dispatched);
        Queue::assertPushed(GenerateImageVariantsJob::class, fn (GenerateImageVariantsJob $job): bool => $job->mediaId === $media->id);

        // Original untouched.
        Storage::disk('local')->assertExists($media->path);
        $this->assertSame($originalBytes, Storage::disk('local')->get($media->path));

        // Old variant files and rows are gone.
        foreach ($oldVariantPaths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        $this->assertSame(0, MediaVariant::query()->where('media_id', $media->id)->count());

        $media->refresh();
        $this->assertNull($media->variants_generated_at);
        $this->assertNull($media->variants_failed_at);
    }

    public function test_regenerate_clears_a_prior_failure_state(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/big.jpg', 1600, 1200);
        $media->variants_failed_at = now();
        $media->save();

        app(RegenerateMediaVariantsAction::class)->handle($media, $admin);

        $media->refresh();
        $this->assertNull($media->variants_failed_at);
    }

    public function test_regenerate_does_nothing_for_non_image_media(): void
    {
        Storage::fake('public');
        Queue::fake();

        $admin = $this->admin();
        $media = new Media;
        $media->disk = 'public';
        $media->path = 'media/documents/report.pdf';
        $media->original_filename = 'report.pdf';
        $media->mime_type = 'application/pdf';
        $media->size = 100;
        $media->visibility = 'public';
        $media->uploader_id = $admin->id;
        Storage::disk('public')->put($media->path, 'pdf-bytes');
        $media->save();

        $dispatched = app(RegenerateMediaVariantsAction::class)->handle($media, $admin);

        $this->assertFalse($dispatched);
        Queue::assertNotPushed(GenerateImageVariantsJob::class);
    }

    public function test_regenerate_does_not_dispatch_for_an_image_that_no_longer_qualifies(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        // Below config('media.variants.min_dimension') of 50.
        $media = $this->storeImage($admin, 'local', 'media/images/tiny.jpg', 10, 10);

        $dispatched = app(RegenerateMediaVariantsAction::class)->handle($media, $admin);

        $this->assertFalse($dispatched);
        Queue::assertNotPushed(GenerateImageVariantsJob::class);
    }

    public function test_regenerate_writes_a_media_variants_regenerated_audit_log_entry(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/big.jpg', 1600, 1200);

        app(RegenerateMediaVariantsAction::class)->handle($media, $admin);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'media.variants_regenerated',
            'entity_type' => 'Media',
            'entity_id' => $media->id,
        ]);

        $log = AuditLog::query()->where('action', 'media.variants_regenerated')->latest('id')->firstOrFail();
        $this->assertSame($media->original_filename, $log->metadata['filename']);
    }

    public function test_regenerate_does_not_write_an_audit_log_entry_when_it_does_nothing(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $media = new Media;
        $media->disk = 'public';
        $media->path = 'media/documents/report.pdf';
        $media->original_filename = 'report.pdf';
        $media->mime_type = 'application/pdf';
        $media->size = 100;
        $media->visibility = 'public';
        $media->uploader_id = $admin->id;
        Storage::disk('public')->put($media->path, 'pdf-bytes');
        $media->save();

        app(RegenerateMediaVariantsAction::class)->handle($media, $admin);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'media.variants_regenerated',
            'entity_id' => $media->id,
        ]);
    }

    public function test_admin_can_regenerate_variants_via_the_edit_page_action(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/big.jpg', 1600, 1200);
        (new GenerateImageVariantsJob($media->id))->handle();

        Queue::fake();

        Livewire::actingAs($admin)
            ->test(EditMedia::class, ['record' => $media->getRouteKey()])
            ->callFormComponentAction('regenerateVariantsActions', 'regenerateVariants')
            ->assertHasNoFormComponentActionErrors();

        Queue::assertPushed(GenerateImageVariantsJob::class);
    }

    public function test_the_page_stops_showing_the_old_variants_immediately_after_regenerating(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();
        $media = $this->storeImage($admin, 'local', 'media/images/big.jpg', 1600, 1200);
        (new GenerateImageVariantsJob($media->id))->handle();

        Queue::fake();

        // Mounting the page here loads (and caches) the `variants` relation
        // on the component's $record — the same object the action closure
        // mutates. Without $record->refresh() in
        // MediaResource::regenerateVariantsAction(), the post-action render
        // below would still show the deleted variant thumbnails.
        Livewire::actingAs($admin)
            ->test(EditMedia::class, ['record' => $media->getRouteKey()])
            ->assertSee('>Generated<', false)
            ->callFormComponentAction('regenerateVariantsActions', 'regenerateVariants')
            ->assertHasNoFormComponentActionErrors()
            ->assertSee('Processing')
            ->assertSee('No variants.')
            ->assertDontSee('>Generated<', false);
    }

    public function test_non_admin_cannot_regenerate_variants(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $nonAdmin = User::factory()->create(['status' => 'active']);
        $media = $this->storeImage($admin, 'local', 'media/images/big.jpg', 1600, 1200);

        $response = $this->actingAs($nonAdmin)->get("/admin/media/{$media->id}/edit");

        $response->assertForbidden();
    }

    private function storeImage(User $uploader, string $disk, string $path, int $width, int $height): Media
    {
        Storage::disk($disk)->put($path, UploadedFile::fake()->image(basename($path), $width, $height)->get());

        return app(StoreUploadedMediaAction::class)->handle($disk, $path, $uploader);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
