<?php

namespace App\Actions\Page\Concerns;

use App\Models\Page;

/**
 * Shared by CreatePageAction/UpdatePageAction. seo_metadata is a separate
 * polymorphic relation (Database Specification §18.6), not a pages column,
 * so it can't be covered by $page->fill()/save() — and since
 * CreatePage/EditPage delegate saving to these Actions rather than
 * Filament's own handleRecordCreation()/handleRecordUpdate(), Filament's
 * automatic relationship-saving for a Group::make([...])->relationship()
 * never runs either. This is the first UI to write to seo_metadata at all.
 */
trait SavesPageSeo
{
    /**
     * @param  array<string, mixed>  $seo
     */
    protected function saveSeo(Page $page, array $seo): void
    {
        if (array_filter($seo, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []) === []) {
            return;
        }

        $page->seo()->updateOrCreate([], $seo);
    }
}
