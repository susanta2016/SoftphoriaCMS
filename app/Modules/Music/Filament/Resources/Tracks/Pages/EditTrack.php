<?php

namespace App\Modules\Music\Filament\Resources\Tracks\Pages;

use App\Filament\Support\Seo\SeoFields;
use App\Models\User;
use App\Modules\Music\Actions\Track\UpdateTrackAction;
use App\Modules\Music\Filament\Resources\Tracks\TrackResource;
use App\Modules\Music\Models\TrackCredit;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditTrack extends EditRecord
{
    protected static string $resource = TrackResource::class;

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
     * release/lyrics/song_story/credits/categoryIds/tagIds/seo aren't real
     * form-bound columns or relationships (see TrackForm's docblock), so
     * their state has to be filled in manually.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['release'] = $this->record->album_id
            ? "album:{$this->record->album_id}"
            : "single:{$this->record->single_id}";

        $data['lyrics'] = [
            'content' => $this->record->lyrics?->content,
            'visibility' => $this->record->lyrics?->visibility ?? 'public',
        ];

        $data['song_story'] = [
            'content' => $this->record->songStory?->content,
            'media_id' => $this->record->songStory?->media_id,
        ];

        $data['credits'] = $this->record->credits
            ->map(fn (TrackCredit $credit): array => [
                'role' => $credit->role,
                'name' => $credit->name,
            ])
            ->all();

        $data['categoryIds'] = $this->record->categories()->pluck('categories.id')->all();
        $data['tagIds'] = $this->record->tags()->pluck('tags.id')->all();

        $seo = $this->record->seo;
        $storedCanonicalUrl = $seo->canonical_url ?? null;
        $path = 'music/tracks/'.(string) ($this->record->slug ?? '');

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

        return app(UpdateTrackAction::class)->handle($record, $data, $actor);
    }
}
