<?php

namespace App\Modules\PoetryProse\Filament\Resources\PoetryProses\Pages;

use App\Filament\Support\Seo\SeoFields;
use App\Models\User;
use App\Modules\PoetryProse\Actions\UpdatePoetryProseAction;
use App\Modules\PoetryProse\Filament\Resources\PoetryProses\PoetryProseResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditPoetryProse extends EditRecord
{
    protected static string $resource = PoetryProseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PoetryProseResource::restoreRevisionAction(),
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * categoryIds/tagIds/seo aren't real form-bound relationships (see
     * PoetryProseForm's docblock reasoning, mirrored from
     * PodcastEpisodeForm), so their state has to be filled in manually.
     * `collection_id` needs no special handling — it's a plain column
     * Filament already fills automatically.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['categoryIds'] = $this->record->categories()->pluck('categories.id')->all();
        $data['tagIds'] = $this->record->tags()->pluck('tags.id')->all();

        $seo = $this->record->seo;
        $storedCanonicalUrl = $seo->canonical_url ?? null;
        $path = 'poetry-prose/'.(string) ($this->record->slug ?? '');

        $data['seo'] = [
            'meta_title' => $seo->meta_title ?? null,
            'meta_description' => $seo->meta_description ?? null,
            'keywords' => $seo->keywords ?? null,
            'canonical_url' => $storedCanonicalUrl ?: SeoFields::autoCanonicalUrl($path),
            'canonical_url_is_auto' => SeoFields::isCanonicalUrlAuto($storedCanonicalUrl, $path),
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(UpdatePoetryProseAction::class)->handle($record, $data, $actor);
    }
}
