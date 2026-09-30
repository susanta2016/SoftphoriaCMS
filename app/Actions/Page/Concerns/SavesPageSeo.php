<?php

namespace App\Actions\Page\Concerns;

use App\Filament\Support\Seo\SeoFields;
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
        // An automatic canonical URL is stored as NULL (same rule as
        // SavesSeoMetadata), so it keeps following config('app.url') and the
        // slug instead of freezing whichever host the page was saved on —
        // e.g. http://localhost:8080 from a dev database.
        if (array_key_exists('canonical_url', $seo) && SeoFields::isCanonicalUrlAuto($seo['canonical_url'], (string) $page->slug)) {
            $seo['canonical_url'] = null;
        }

        if (array_filter($seo, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []) === []
            && ! $page->seo()->exists()) {
            return;
        }

        $page->seo()->updateOrCreate([], $seo);
    }
}
