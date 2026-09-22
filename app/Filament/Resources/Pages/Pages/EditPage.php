<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Actions\Page\UpdatePageAction;
use App\Enums\PageSectionType;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Support\Seo\SeoFields;
use App\Models\PageSection;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
            $this->getCancelFormAction(),
            PageResource::deletePageAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    /**
     * sections/seo aren't real form-bound relationships (see PageForm's
     * docblock), so their state has to be filled in manually.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['sections'] = $this->record->sections()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PageSection $section): array => [
                'id' => $section->id,
                'section_type' => $section->section_type,
                'title' => $section->title,
                'is_enabled' => $section->is_enabled,
                'content_json' => $this->hydrateGalleryItems($section),
            ])
            ->all();

        $seo = $this->record->seo;
        $storedCanonicalUrl = $seo->canonical_url ?? null;
        $slug = (string) ($this->record->slug ?? '');

        $data['seo'] = [
            'meta_title' => $seo->meta_title ?? null,
            'meta_description' => $seo->meta_description ?? null,
            'keywords' => $seo->keywords ?? null,
            'canonical_url' => $storedCanonicalUrl ?: SeoFields::autoCanonicalUrl($slug),
            'canonical_url_is_auto' => SeoFields::isCanonicalUrlAuto($storedCanonicalUrl, $slug),
            'robots' => $seo->robots ?? null,
            'og_title' => $seo->og_title ?? null,
            'og_description' => $seo->og_description ?? null,
            'og_image_media_id' => $seo->og_image_media_id ?? null,
            'twitter_title' => $seo->twitter_title ?? null,
            'twitter_description' => $seo->twitter_description ?? null,
            'twitter_image_media_id' => $seo->twitter_image_media_id ?? null,
        ];

        return $data;
    }

    /**
     * WEB-101 item E — a Gallery section saved before the Gallery repeater
     * existed only has the old flat content_json.media_ids array. Hydrate
     * it into the new content_json.gallery_items shape here, once, on
     * load, so editing an old section shows its existing images in the
     * repeater instead of an empty list. The public page
     * (resources/views/pages/show.blade.php) still renders the old shape
     * unchanged until the section is actually re-saved through this form.
     *
     * @return array<string, mixed>|null
     */
    private function hydrateGalleryItems(PageSection $section): ?array
    {
        $content = $section->content_json;

        if ($section->section_type !== PageSectionType::Gallery->value) {
            return $content;
        }

        if (! empty($content['gallery_items'] ?? null) || empty($content['media_ids'] ?? null)) {
            return $content;
        }

        $content['gallery_items'] = collect($content['media_ids'])
            ->map(fn ($id) => ['media_id' => $id, 'title' => null, 'description' => null, 'url' => null])
            ->all();

        return $content;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(UpdatePageAction::class)->handle($record, $data, $actor);
    }
}
