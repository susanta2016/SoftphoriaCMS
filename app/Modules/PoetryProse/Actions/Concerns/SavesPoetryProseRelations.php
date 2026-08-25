<?php

namespace App\Modules\PoetryProse\Actions\Concerns;

use App\Modules\PoetryProse\Models\PoetryProse;

/**
 * Shared by CreatePoetryProseAction/UpdatePoetryProseAction —
 * seo_metadata, poetry_prose_categories, and poetry_prose_tags are all
 * separate tables, not poetry_prose columns, so plain fill()/save() never
 * touches them. Mirrors SavesPodcastEpisodeRelations exactly.
 */
trait SavesPoetryProseRelations
{
    /**
     * @param  array<string, mixed>  $seo
     */
    protected function saveSeo(PoetryProse $entry, array $seo): void
    {
        if (array_filter($seo, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []) === []) {
            return;
        }

        $entry->seo()->updateOrCreate([], $seo);
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    protected function syncCategories(PoetryProse $entry, array $categoryIds): void
    {
        $entry->categories()->sync($categoryIds);
    }

    /**
     * @param  array<int, int>  $tagIds
     */
    protected function syncTags(PoetryProse $entry, array $tagIds): void
    {
        $entry->tags()->sync($tagIds);
    }

    // No syncCollections() — `collection_id` is a plain fillable column
    // (one collection per entry, client-confirmed, final), so it flows
    // through the normal fill()/save() call in Create/UpdatePoetryProseAction
    // like any other scalar field, unlike categories/tags/seo above.
}
