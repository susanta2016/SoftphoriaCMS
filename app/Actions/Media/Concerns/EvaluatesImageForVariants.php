<?php

namespace App\Actions\Media\Concerns;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Shared by StoreUploadedMediaAction and ReplaceMediaFileAction (ADMIN-005)
 * so both agree on exactly one definition of "does this image qualify for
 * derived variants" instead of drifting apart.
 */
trait EvaluatesImageForVariants
{
    /**
     * Animated GIFs and images below the configured minimum dimension are
     * skipped entirely — the original is kept as the only asset, no derived
     * files are generated (config('media.variants.min_dimension')).
     */
    protected function qualifiesForVariants(Media $media): bool
    {
        $absolutePath = Storage::disk($media->disk)->path($media->path);

        if ($media->mime_type === 'image/gif' && $this->isAnimatedGif($absolutePath)) {
            return false;
        }

        $dimensions = @getimagesize($absolutePath);

        if ($dimensions === false) {
            return false;
        }

        [$width, $height] = $dimensions;
        $minDimension = config('media.variants.min_dimension');

        return $width >= $minDimension && $height >= $minDimension;
    }

    /**
     * A GIF is animated when its byte stream contains more than one Graphic
     * Control Extension block (0x21 0xF9 0x04) — the standard lightweight
     * heuristic for frame counting without decoding the whole image.
     */
    private function isAnimatedGif(string $absolutePath): bool
    {
        $contents = @file_get_contents($absolutePath);

        if ($contents === false) {
            return false;
        }

        return substr_count($contents, "\x21\xF9\x04") > 1;
    }
}
