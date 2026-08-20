<?php

namespace App\Modules\Music\Filament\Resources\Albums\Pages;

use App\Filament\Support\Seo\SeoFields;
use App\Models\User;
use App\Modules\Music\Actions\Album\UpdateAlbumAction;
use App\Modules\Music\Filament\Resources\Albums\AlbumResource;
use App\Modules\Music\Models\MusicStreamingLink;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditAlbum extends EditRecord
{
    protected static string $resource = AlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * links/seo aren't real form-bound relationships (see AlbumForm's
     * docblock), so their state has to be filled in manually.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['links'] = $this->record->streamingLinks
            ->map(fn (MusicStreamingLink $link): array => [
                'provider' => $link->provider->value,
                'url' => $link->url,
            ])
            ->all();

        $seo = $this->record->seo;
        $storedCanonicalUrl = $seo->canonical_url ?? null;
        $path = 'music/'.(string) ($this->record->slug ?? '');

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

        return app(UpdateAlbumAction::class)->handle($record, $data, $actor);
    }
}
