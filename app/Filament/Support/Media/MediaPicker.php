<?php

namespace App\Filament\Support\Media;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Enums\MediaCategory;
use App\Models\Media;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * ADMIN-006 review fix — the single, global entry point every Admin module
 * must use whenever a field needs to reference a Media Library asset.
 * Provides two explicit choices: "Upload New Media" (goes through the exact
 * same StoreUploadedMediaAction/config('media.categories') rules as the
 * Media Library's own UploadMedia page — never a parallel upload path) and
 * "Select from Media Library" (opens the visual grid browser in
 * libraryBrowserSchema() below — never a plain searchable Select). See
 * docs/ARCHITECTURE.md §14 — no module may build its own media
 * upload/selection mechanism instead of calling MediaPicker::make().
 */
class MediaPicker
{
    public const int PER_PAGE = 24;

    /**
     * @param  array<int, Component>  $extraSchema  Additional components rendered inside this same Fieldset, after the picker's own actions — e.g. a read-only detail about the selected file. Empty by default; every existing call site is unaffected.
     */
    public static function make(
        string $name,
        string $label,
        MediaCategory $category = MediaCategory::Image,
        bool $multiple = false,
        array $extraSchema = [],
    ): Fieldset {
        return Fieldset::make($label)
            ->columns(1)
            ->schema([
                Hidden::make($name),
                Placeholder::make("{$name}__preview")
                    ->hiddenLabel()
                    ->content(fn (Get $get): HtmlString => self::preview($get($name), $multiple, $category)),
                Actions::make([
                    self::uploadAction($name, $category, $multiple),
                    self::selectAction($name, $category, $multiple),
                    self::clearAction($name, $multiple),
                ])->key(self::actionKey($name).'__actions'),
                ...$extraSchema,
            ]);
    }

    /**
     * Action names are resolved on a dot-separated path by Filament's
     * action-lookup/testing helpers (action.nestedModalAction) — a literal
     * dot in the action's own name, which a dotted field $name like
     * "seo.og_image_media_id" or "content_json.media_id" would otherwise
     * produce, breaks that lookup. The field's own state path (Hidden/
     * Get/Set) keeps the real dotted $name; only action identifiers use
     * this sanitized form.
     */
    private static function actionKey(string $name): string
    {
        return str_replace('.', '_', $name);
    }

    public static function uploadAction(string $name, MediaCategory $category, bool $multiple = false): Action
    {
        return Action::make(self::actionKey($name).'_upload')
            ->label('Upload New Media')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->modalHeading('Upload New Media')
            ->modalWidth(Width::Medium)
            ->schema([
                FileUpload::make('file')
                    ->label('File')
                    ->required()
                    ->disk($category->diskName())
                    ->directory($category->directory())
                    ->visibility($category->visibility())
                    ->maxSize($category->maxSizeKb())
                    ->acceptedFileTypes($category->acceptedMimeTypes()),
                TextInput::make('alt_text')
                    ->label('Alt text')
                    ->maxLength(255)
                    ->helperText('Optional. Used for images.'),
            ])
            ->action(function (array $data, Set $set, Get $get) use ($name, $category, $multiple): void {
                /** @var User $actor */
                $actor = Auth::user();

                $media = app(StoreUploadedMediaAction::class)->handle(
                    disk: $category->diskName(),
                    path: $data['file'],
                    uploader: $actor,
                    visibility: $category->visibility(),
                );

                if (filled($data['alt_text'] ?? null)) {
                    $media->alt_text = $data['alt_text'];
                    $media->save();
                }

                if ($multiple) {
                    $set($name, [...(array) ($get($name) ?? []), $media->id]);
                } else {
                    $set($name, $media->id);
                }

                Notification::make()->title('Uploaded to Media Library')->success()->send();
            });
    }

    public static function selectAction(string $name, MediaCategory $category, bool $multiple = false): Action
    {
        return Action::make(self::actionKey($name).'_select')
            ->label('Select from Media Library')
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->modalHeading('Select from Media Library')
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel($multiple ? 'Add Selected' : 'Select')
            ->fillForm(fn (Get $get): array => [
                'search' => '',
                'take' => self::PER_PAGE,
                'media' => $get($name),
            ])
            ->schema(self::libraryBrowserSchema($category, $multiple))
            ->action(function (array $data, Set $set) use ($name, $multiple): void {
                $set($name, $multiple ? ($data['media'] ?? []) : ($data['media'] ?? null));
            });
    }

    public static function clearAction(string $name, bool $multiple = false): Action
    {
        return Action::make(self::actionKey($name).'_clear')
            ->label('Remove')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (Get $get): bool => filled($get($name)))
            ->requiresConfirmation()
            ->action(fn (Set $set) => $set($name, $multiple ? [] : null));
    }

    /**
     * The reusable "browse the Media Library" widget: a live-filtered visual
     * grid that shows existing media immediately (no search required first),
     * plus an optional search box and a "Load more" action. Embedded as-is
     * inside both selectAction()'s own modal and RichEditorMediaAttachments'
     * attach modal, so there is exactly one media-browsing implementation
     * throughout the admin (docs/ARCHITECTURE.md §14) — never a per-consumer
     * reimplementation of "browse and pick a Media row".
     *
     * @return array<int, Component>
     */
    public static function libraryBrowserSchema(MediaCategory $category, bool $multiple, string $fieldName = 'media', bool|Closure $required = true): array
    {
        return [
            TextInput::make('search')
                ->label('Search')
                ->placeholder('Search by filename (optional)')
                ->live(debounce: '500ms')
                ->dehydrated(false)
                ->columnSpanFull(),
            Hidden::make('take')->default(self::PER_PAGE)->dehydrated(false),
            ViewField::make($fieldName)
                ->hiddenLabel()
                ->required($required)
                ->validationAttribute('a media item')
                ->view('filament.forms.components.media-library-grid')
                ->viewData(fn (Get $get): array => self::gridViewData(
                    $category,
                    $multiple,
                    (string) ($get('search') ?? ''),
                    (int) ($get('take') ?? self::PER_PAGE),
                ))
                ->columnSpanFull(),
            Actions::make([
                Action::make('loadMore')
                    ->label('Load more')
                    ->link()
                    ->visible(fn (Get $get): bool => self::query($category, (string) ($get('search') ?? ''))
                        ->count() > (int) ($get('take') ?? self::PER_PAGE))
                    ->action(fn (Set $set, Get $get) => $set('take', (int) ($get('take') ?? self::PER_PAGE) + self::PER_PAGE)),
            ])->columnSpanFull(),
        ];
    }

    /**
     * The exact query the grid browser is built from — category filtering
     * happens here, at the query level (never by hiding cards client-side),
     * per docs/ARCHITECTURE.md §14. Public so tests (and any future
     * consumer that just needs a count/list rather than the full picker
     * UI) can assert against the same query the grid actually renders.
     */
    public static function query(MediaCategory $category, string $search = ''): Builder
    {
        return Media::query()
            ->whereIn('mime_type', $category->acceptedMimeTypes())
            ->when($search !== '', fn (Builder $query) => $query->where('original_filename', 'like', "%{$search}%"))
            ->orderByDesc('created_at');
    }

    /**
     * @return array{items: Collection<int, Media>, multiple: bool}
     */
    private static function gridViewData(MediaCategory $category, bool $multiple, string $search, int $take): array
    {
        return [
            'items' => self::query($category, $search)->limit($take)->get(),
            'multiple' => $multiple,
        ];
    }

    private static function preview(mixed $value, bool $multiple, MediaCategory $category): HtmlString
    {
        $ids = $multiple ? array_filter((array) $value) : array_filter([$value]);

        if ($ids === []) {
            return MediaPreview::empty("No {$category->getLabel()} selected.");
        }

        $items = Media::query()->whereIn('id', $ids)->get()->keyBy('id');

        $html = '<div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center">';

        foreach ($ids as $id) {
            /** @var ?Media $media */
            $media = $items->get($id);

            if (! $media) {
                continue;
            }

            $html .= match ($media->category()) {
                MediaCategory::Image => sprintf(
                    '<img src="%s" style="width:64px;height:64px;object-fit:cover;border-radius:0.375rem;border:1px solid #e5e7eb" alt="%s" title="%s" />',
                    e(Storage::disk($media->disk)->url($media->path)),
                    e($media->alt_text ?? $media->original_filename),
                    e($media->original_filename),
                ),
                // Both streamed through the existing admin-only media.stream
                // route (StreamMediaController) — never the public disk —
                // same as MediaForm's own preview and the Media Library
                // grid. This is what gives any audio_media_id/video_media_id
                // field (Track, PodcastEpisode) a real player automatically,
                // with no per-field UI code needed.
                MediaCategory::Audio => (string) MediaPreview::audioPlayer($media),
                MediaCategory::Video => (string) MediaPreview::videoPlayer($media),
                default => sprintf(
                    '<span style="display:inline-block;padding:0.375rem 0.5rem;border:1px solid #e5e7eb;border-radius:0.375rem;font-size:0.875rem">%s</span>',
                    e($media->original_filename),
                ),
            };
        }

        $html .= '</div>';

        return new HtmlString($html);
    }
}
