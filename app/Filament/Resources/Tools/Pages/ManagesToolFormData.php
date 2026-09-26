<?php

namespace App\Filament\Resources\Tools\Pages;

use App\Filament\Support\Seo\SavesSeoMetadata;
use App\Models\Tool;

/**
 * Create/Edit Tool: the fields that aren't plain tool columns — SEO (shared
 * seo_metadata row, via SavesSeoMetadata), its share image, and the ordered
 * related-tools list.
 */
trait ManagesToolFormData
{
    use SavesSeoMetadata;

    private ?int $pendingOgImage = null;

    /** @var list<int> */
    private array $pendingRelated = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function pullToolExtras(array $data): array
    {
        $this->pendingOgImage = filled($data['og_image_media_id'] ?? null) ? (int) $data['og_image_media_id'] : null;
        $this->pendingRelated = array_values(array_map('intval', $data['related_tool_ids'] ?? []));
        unset($data['og_image_media_id'], $data['related_tool_ids']);

        return $this->pullSeo($data);
    }

    protected function persistToolExtras(Tool $tool): void
    {
        $this->persistSeo($tool, 'tools/'.$tool->slug);

        // The share image lives on the same row (not one of SavesSeoMetadata's
        // text fields); persistSeo() may just have removed an empty one.
        if ($this->pendingOgImage !== null) {
            $seo = $tool->seo()->firstOrNew();
            $seo->forceFill(['og_image_media_id' => $this->pendingOgImage]);
            $tool->seo()->save($seo);
        } else {
            $tool->seo()->update(['og_image_media_id' => null]);
        }

        $tool->relatedTools()->sync(collect($this->pendingRelated)
            ->reject(fn (int $id): bool => $id === $tool->getKey())
            ->values()
            ->mapWithKeys(fn (int $id, int $i): array => [$id => ['sort_order' => $i]])
            ->all());
    }
}
