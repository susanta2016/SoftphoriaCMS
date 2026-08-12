<?php

namespace App\Actions\Media;

use App\Actions\Media\Concerns\DeletesMediaVariants;
use App\Actions\Media\Concerns\EvaluatesImageForVariants;
use App\Enums\MediaCategory;
use App\Jobs\Media\GenerateImageVariantsJob;
use App\Models\Media;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Manual "Regenerate Variants" trigger on the Media Edit page (ADMIN-005
 * review fix) — for re-running the pipeline after a failure, or after
 * config('media.variants') changes. The original upload is never touched;
 * only derived variants are removed and rebuilt via the existing
 * GenerateImageVariantsJob, so this deliberately does not duplicate any
 * image-processing logic.
 *
 * Still gated by EvaluatesImageForVariants::qualifiesForVariants() before
 * dispatching — GenerateImageVariantsJob itself has no animated-GIF or
 * minimum-dimension awareness (that check only ever happens in the callers
 * that decide *whether* to dispatch it), so skipping this gate here would
 * silently generate static derivatives of an animated GIF.
 */
class RegenerateMediaVariantsAction
{
    use DeletesMediaVariants, EvaluatesImageForVariants;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @return bool whether a new generation job was actually dispatched
     */
    public function handle(Media $media, User $actor): bool
    {
        if ($media->category() !== MediaCategory::Image) {
            return false;
        }

        DB::transaction(function () use ($media): void {
            $this->deleteExistingVariants($media);

            $media->variants_generated_at = null;
            $media->variants_failed_at = null;
            $media->save();
        });

        if (! $this->qualifiesForVariants($media)) {
            return false;
        }

        $this->auditLog->record($actor, 'media.variants_regenerated', $media, [
            'filename' => $media->original_filename,
        ]);

        GenerateImageVariantsJob::dispatch($media->id);

        return true;
    }
}
