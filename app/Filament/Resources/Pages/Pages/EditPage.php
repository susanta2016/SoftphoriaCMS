<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Actions\Page\UpdatePageAction;
use App\Filament\Resources\Pages\PageResource;
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
                'content_json' => $section->content_json,
            ])
            ->all();

        $seo = $this->record->seo;
        $data['seo'] = $seo ? [
            'meta_title' => $seo->meta_title,
            'meta_description' => $seo->meta_description,
            'canonical_url' => $seo->canonical_url,
            'robots' => $seo->robots,
            'og_title' => $seo->og_title,
            'og_description' => $seo->og_description,
            'og_image_media_id' => $seo->og_image_media_id,
            'twitter_title' => $seo->twitter_title,
            'twitter_description' => $seo->twitter_description,
            'twitter_image_media_id' => $seo->twitter_image_media_id,
        ] : [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(UpdatePageAction::class)->handle($record, $data, $actor);
    }
}
