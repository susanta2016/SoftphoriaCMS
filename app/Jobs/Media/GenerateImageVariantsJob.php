<?php

namespace App\Jobs\Media;

use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Throwable;

/**
 * Runs on the existing Redis queue (CORE-SVC-001/002) so uploads aren't
 * blocked on image processing. Dispatched only for images that
 * StoreUploadedMediaAction determined qualify (not animated GIF, not below
 * the configured minimum dimension). The original Media file is never
 * touched — every output here is a new file referenced by a MediaVariant
 * row (ADMIN-005).
 *
 * variants_generated_at / variants_failed_at track processing status for
 * the admin UI (Media Edit page's Responsive Variants section): both null
 * means pending/processing, generated_at set means success, failed_at set
 * means the queue worker exhausted retries and called failed() below.
 */
class GenerateImageVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $mediaId) {}

    public function handle(): void
    {
        $media = Media::query()->find($this->mediaId);

        if (! $media) {
            return;
        }

        $absolutePath = Storage::disk($media->disk)->path($media->path);
        $originalDimensions = @getimagesize($absolutePath);

        if ($originalDimensions === false) {
            return;
        }

        $originalWidth = $originalDimensions[0];
        $manager = new ImageManager(new Driver);
        $formats = collect(config('media.variants.formats'))->filter()->keys();

        $thumbnail = config('media.variants.thumbnail');
        $this->generateRendition($manager, $media, $absolutePath, 'thumbnail', $thumbnail['width'], $thumbnail['height'], $formats);

        foreach (config('media.variants.responsive_widths') as $width) {
            if ($originalWidth < $width) {
                continue;
            }

            $this->generateRendition($manager, $media, $absolutePath, 'responsive', $width, null, $formats);
        }

        $media->variants_generated_at = now();
        $media->variants_failed_at = null;
        $media->save();
    }

    /**
     * Laravel's standard queue failure hook — called once retries are
     * exhausted (no explicit $tries here, so that's after the first
     * attempt). Surfaces as "Failed" in the admin UI instead of leaving the
     * record looking like it's still processing forever.
     */
    public function failed(?Throwable $exception): void
    {
        $media = Media::query()->find($this->mediaId);

        if (! $media) {
            return;
        }

        $media->variants_failed_at = now();
        $media->save();
    }

    /**
     * @param  Collection<int, string>  $formats
     */
    private function generateRendition(
        ImageManager $manager,
        Media $media,
        string $absolutePath,
        string $type,
        int $width,
        ?int $height,
        $formats,
    ): void {
        foreach ($formats as $format) {
            // Fresh decode per output file: resize operations mutate the
            // image in place, so reusing one decoded instance across
            // renditions would compound each resize onto the last.
            $image = $manager->decodePath($absolutePath);

            if ($type === 'thumbnail') {
                $image->coverDown($width, $height);
            } else {
                $image->scaleDown(width: $width);
            }

            $encoded = $image->encode($this->encoderFor($format));
            $variantPath = $this->variantPath($media, $type, $width, $format);

            Storage::disk($media->disk)->put($variantPath, $encoded->toString());

            MediaVariant::query()->create([
                'media_id' => $media->id,
                'type' => $type,
                'width' => $image->width(),
                'height' => $image->height(),
                'format' => $format,
                'disk' => $media->disk,
                'path' => $variantPath,
                'size' => $encoded->size(),
            ]);
        }
    }

    private function encoderFor(string $format): EncoderInterface
    {
        return match ($format) {
            'webp' => new WebpEncoder(quality: 82),
            'avif' => new AvifEncoder(quality: 60),
        };
    }

    private function variantPath(Media $media, string $type, int $width, string $format): string
    {
        $directory = pathinfo($media->path, PATHINFO_DIRNAME);
        $filename = pathinfo($media->path, PATHINFO_FILENAME);

        return "{$directory}/variants/{$filename}-{$type}-{$width}.{$format}";
    }
}
