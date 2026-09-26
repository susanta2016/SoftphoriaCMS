<?php

namespace App\Filament\Support\Seo;

use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Model;

/**
 * For Filament Create/Edit record pages whose model has a polymorphic
 * seo(): MorphOne (the shared seo_metadata table) and whose form holds the
 * SEO fields under "seo.*" — the Blog resources. Filament's own
 * relationship saving isn't used because the "seo" keys aren't model
 * attributes; instead the page pulls them out before save and writes the
 * relation after.
 *
 * An automatic canonical URL is stored as NULL, so the canonical keeps
 * following the record's real URL if its slug changes later.
 */
trait SavesSeoMetadata
{
    private const SEO_FIELDS = ['meta_title', 'meta_description', 'keywords', 'canonical_url', 'robots', 'og_title', 'og_description'];

    /** @var array<string, mixed> */
    protected array $pendingSeo = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function pullSeo(array $data): array
    {
        $this->pendingSeo = $data['seo'] ?? [];
        unset($data['seo']);

        return $data;
    }

    /**
     * @param  string  $autoPath  the record's own public path (e.g. "blog/my-post")
     */
    protected function persistSeo(Model $record, string $autoPath): void
    {
        $seo = collect(self::SEO_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => filled($this->pendingSeo[$field] ?? null) ? $this->pendingSeo[$field] : null])
            ->all();

        // canonicalUrlFields()'s "is auto" flag isn't dehydrated, so decide
        // from the value: the record's own URL means "automatic".
        if (SeoFields::isCanonicalUrlAuto($seo['canonical_url'], $autoPath)) {
            $seo['canonical_url'] = null;
        }

        if (array_filter($seo) === []) {
            $record->seo()->delete();

            return;
        }

        $record->seo()->updateOrCreate([], $seo);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillSeo(array $data, ?SeoMetadata $seo, string $autoPath): array
    {
        $stored = $seo?->canonical_url;

        $data['seo'] = [
            ...collect(self::SEO_FIELDS)->mapWithKeys(fn (string $field): array => [$field => $seo?->{$field}])->all(),
            'canonical_url' => $stored ?: SeoFields::autoCanonicalUrl($autoPath),
            'canonical_url_is_auto' => SeoFields::isCanonicalUrlAuto($stored, $autoPath),
        ];

        return $data;
    }
}
