<?php

namespace App\Modules\Music\Actions\Concerns;

use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;

/**
 * Shared by Album's, Single's and Track's Create/UpdateAction pairs — all
 * three models expose an identically-shaped seo(): MorphOne(SeoMetadata),
 * so this is the single place that reconciles it rather than repeating the
 * same "skip if every field is blank, otherwise updateOrCreate" logic three
 * times.
 */
trait SavesMusicSeo
{
    /**
     * @param  array<string, mixed>  $seo
     */
    protected function saveMusicSeo(Album|Single|Track $model, array $seo): void
    {
        if (array_filter($seo, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []) === []) {
            return;
        }

        $model->seo()->updateOrCreate([], $seo);
    }
}
