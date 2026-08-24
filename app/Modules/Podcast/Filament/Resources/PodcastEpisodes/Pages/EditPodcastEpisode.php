<?php

namespace App\Modules\Podcast\Filament\Resources\PodcastEpisodes\Pages;

use App\Filament\Support\Seo\SeoFields;
use App\Models\User;
use App\Modules\Podcast\Actions\PodcastEpisode\UpdatePodcastEpisodeAction;
use App\Modules\Podcast\Filament\Resources\PodcastEpisodes\PodcastEpisodeResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditPodcastEpisode extends EditRecord
{
    protected static string $resource = PodcastEpisodeResource::class;

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
     * links/seo aren't real form-bound relationships (see PodcastEpisodeForm's
     * docblock), so their state has to be filled in manually.
     *
     * The form only presents a single streaming link (state path
     * `links.0`), so only the first link (by sort_order — see
     * PodcastEpisode::links()) is shown here even if this episode has more
     * than one stored from before the client's single-link rule. Any
     * additional link this form doesn't show is left untouched on save —
     * see SavesPodcastEpisodeRelations::syncPrimaryLink() — never silently
     * deleted just because the episode was edited.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $firstLink = $this->record->links->first();

        $data['links'] = $firstLink
            ? [['provider' => $firstLink->provider->value, 'url' => $firstLink->url]]
            : [];

        $data['categoryIds'] = $this->record->categories()->pluck('categories.id')->all();
        $data['tagIds'] = $this->record->tags()->pluck('tags.id')->all();

        $seo = $this->record->seo;
        $storedCanonicalUrl = $seo->canonical_url ?? null;
        $path = 'podcast/'.(string) ($this->record->slug ?? '');

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

        return app(UpdatePodcastEpisodeAction::class)->handle($record, $data, $actor);
    }
}
