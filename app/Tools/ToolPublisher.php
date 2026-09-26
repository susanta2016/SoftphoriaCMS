<?php

namespace App\Tools;

use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolRedirect;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Publication state changes for tools. Publishing only flips the row's
 * status — it never touches code, runs commands or deploys anything; the
 * functionality must already be deployed (checked through ToolRegistry).
 */
class ToolPublisher
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ToolSettings $settings,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Required before publishing. Labels of what's missing; empty when ready.
     *
     * @return list<string>
     */
    public function missingRequirements(Tool $tool): array
    {
        $tool->loadMissing('seo');

        return array_keys(array_filter([
            'Tool name' => blank($tool->name),
            'Slug' => blank($tool->slug),
            'Category' => blank($tool->tool_category_id),
            'Tool functionality' => blank($tool->functionality),
            'A tool functionality available in this deployment' => filled($tool->functionality) && ! $this->registry->has($tool->functionality),
            'Page heading (H1)' => blank($tool->heading),
            'SEO title' => blank($tool->seo?->meta_title),
            'Meta description' => blank($tool->seo?->meta_description),
        ]));
    }

    /**
     * Recommended but never blocking.
     *
     * @return list<string>
     */
    public function missingRecommendations(Tool $tool): array
    {
        return array_keys(array_filter([
            'Introduction' => blank($tool->introduction),
            'How it works' => blank(strip_tags((string) $tool->how_it_works)),
            'FAQ' => ! $tool->faqs()->where('is_visible', true)->exists(),
            'Related tool' => ! $tool->relatedTools()->exists(),
            'Related service' => blank($tool->service_id),
            'Call to action' => blank($tool->cta_heading) && blank($this->settings->get('cta_heading')),
        ]));
    }

    /**
     * @return list<string> the missing requirements; empty when published
     */
    public function publish(Tool $tool, ?User $actor): array
    {
        $missing = $this->missingRequirements($tool);

        if ($missing !== []) {
            return $missing;
        }

        DB::transaction(function () use ($tool, $actor): void {
            $tool->forceFill([
                'status' => ToolStatus::Published,
                'published_at' => $tool->published_at ?? now(),
                'updated_by' => $actor?->getKey(),
            ])->save();

            // A live tool now owns this slug; an old redirect using it would
            // never be reached again.
            ToolRedirect::query()->where('old_slug', $tool->slug)->delete();

            $this->auditLog->record($actor, 'tool.published', $tool, ['slug' => $tool->slug, 'functionality' => $tool->functionality]);
        });

        return [];
    }

    public function unpublish(Tool $tool, ?User $actor): void
    {
        $tool->forceFill(['status' => ToolStatus::Unpublished, 'updated_by' => $actor?->getKey()])->save();

        $this->auditLog->record($actor, 'tool.unpublished', $tool, ['slug' => $tool->slug]);
    }

    /**
     * After a slug change: if the tool has ever been public, its old URL
     * 301s to the new one.
     */
    public function recordSlugChange(Tool $tool, string $oldSlug): void
    {
        if ($oldSlug === $tool->slug || $tool->published_at === null) {
            return;
        }

        ToolRedirect::query()->updateOrCreate(['old_slug' => $oldSlug], ['tool_id' => $tool->getKey()]);
        ToolRedirect::query()->where('old_slug', $tool->slug)->delete();
    }

    /**
     * A new Draft copying the content, SEO, FAQ, CTA and related content.
     */
    public function duplicate(Tool $source, string $name, string $slug, ?string $functionality, ?User $actor): Tool
    {
        return DB::transaction(function () use ($source, $name, $slug, $functionality, $actor): Tool {
            $source->loadMissing(['seo', 'faqs', 'relatedTools']);

            $copy = $source->replicate(['status', 'published_at', 'is_featured', 'created_by', 'updated_by', 'created_at', 'updated_at']);
            $copy->fill([
                'name' => $name,
                'slug' => $slug,
                'functionality' => $functionality,
                'created_by' => $actor?->getKey(),
                'updated_by' => $actor?->getKey(),
            ]);
            $copy->status = ToolStatus::Draft;
            $copy->is_featured = false;
            $copy->save();

            if ($source->seo) {
                $seo = $source->seo->replicate(['seoable_id', 'seoable_type', 'created_at', 'updated_at']);
                $seo->canonical_url = null;
                $copy->seo()->save($seo);
            }

            foreach ($source->faqs as $faq) {
                $copy->faqs()->create($faq->only(['question', 'answer', 'is_visible', 'sort_order']));
            }

            $copy->relatedTools()->sync($source->relatedTools
                ->mapWithKeys(fn (Tool $related): array => [$related->id => ['sort_order' => $related->pivot->sort_order]])
                ->all());

            $this->auditLog->record($actor, 'tool.duplicated', $copy, ['from' => $source->slug]);

            return $copy;
        });
    }
}
