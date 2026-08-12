<?php

namespace App\Filament\Resources\Media;

use App\Actions\Media\RegenerateMediaVariantsAction;
use App\Actions\Media\ReplaceMediaFileAction;
use App\Enums\MediaCategory;
use App\Exceptions\Media\MediaCategoryMismatchException;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Media\Pages\UploadMedia;
use App\Filament\Resources\Media\Schemas\MediaForm;
use App\Filament\Resources\Media\Tables\MediaTable;
use App\Models\Media;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * ADMIN-005: Media Library — upload/validate/store, browse/reuse, image
 * optimization (WebP/AVIF responsive variants via GenerateImageVariantsJob),
 * and admin-side audio/video playback (StreamMediaController). No CDN,
 * watermarking, transcoding, or public streaming — see the Implementation
 * Guide's ADMIN-005 scope and the approved ADMIN-005 implementation plan.
 *
 * View and Edit are one page (EditMedia), not two — per docs/Reference
 * UI/Admin/Media Edit-details.docx. There is no separate ViewRecord page or
 * infolist(); MediaForm carries both the read-only details and the
 * editable fields.
 */
class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'original_filename';

    public static function form(Schema $schema): Schema
    {
        return MediaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MediaTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'create' => UploadMedia::route('/upload'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }

    /**
     * ADMIN-005 review fix: embedded in the Edit form (MediaForm) via
     * Actions::make([...]) — the ARCHITECTURE.md §13 "record-scoped quick
     * actions embedded in the form" pattern, same shape as
     * UserResource::blockAction() etc. Category-specific disk/directory/
     * size/accepted-types are resolved once per mount from
     * config('media.categories'), keyed by the record's OWN current
     * category — this is what enforces "replacement must stay within the
     * same category" at the form level (ReplaceMediaFileAction enforces it
     * again at the domain layer as the authoritative check).
     */
    public static function replaceFileAction(): Action
    {
        return Action::make('replaceFile')
            ->label('Replace File')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->schema(function (Media $record): array {
                $categoryConfig = config("media.categories.{$record->category()?->value}", []);

                return [
                    FileUpload::make('file')
                        ->label('New file')
                        ->required()
                        ->disk($categoryConfig['disk'] ?? $record->disk)
                        ->directory($categoryConfig['directory'] ?? dirname($record->path))
                        ->visibility($record->visibility === 'public' ? 'public' : 'private')
                        ->maxSize($categoryConfig['max_size'] ?? null)
                        ->acceptedFileTypes($categoryConfig['accepted_mime_types'] ?? [])
                        ->helperText('Must be the same type of file (image/document/audio/video) as the one it replaces.'),
                ];
            })
            ->requiresConfirmation()
            ->modalDescription(fn (Media $record): string => "Replace \"{$record->original_filename}\"? The old file and its variants will be permanently removed once the new one is stored.")
            ->action(function (Media $record, array $data): void {
                /** @var User $actor */
                $actor = Auth::user();

                $categoryConfig = config("media.categories.{$record->category()?->value}", []);

                try {
                    app(ReplaceMediaFileAction::class)->handle(
                        media: $record,
                        disk: $categoryConfig['disk'] ?? $record->disk,
                        path: $data['file'],
                        actor: $actor,
                    );

                    // $record is the same instance Livewire keeps (and
                    // re-serializes) across requests, so its already-loaded
                    // `variants` relation still holds the pre-replace rows
                    // deleteExistingVariants() just removed via a separate
                    // query — without this the Preview/Responsive Variants
                    // placeholders keep rendering stale data until a manual
                    // page reload.
                    $record->refresh();

                    Notification::make()
                        ->title('File replaced')
                        ->success()
                        ->send();
                } catch (MediaCategoryMismatchException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * ADMIN-005 review fix: manual retrigger for the Responsive Variants
     * section. Only ever shown for image category (the section itself is
     * ->visible() gated the same way), and RegenerateMediaVariantsAction
     * still re-checks eligibility (animated GIF / below minimum dimension)
     * before dispatching, so this can't be used to force variants onto
     * something that was correctly skipped originally.
     */
    public static function regenerateVariantsAction(): Action
    {
        return Action::make('regenerateVariants')
            ->label('Regenerate Variants')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Media $record): bool => $record->category() === MediaCategory::Image)
            ->requiresConfirmation()
            ->modalDescription('Delete the current thumbnail and responsive variants and regenerate them from the original file? The original upload itself is never changed.')
            ->action(function (Media $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                $dispatched = app(RegenerateMediaVariantsAction::class)->handle($record, $actor);

                $record->refresh();

                if ($dispatched) {
                    Notification::make()
                        ->title('Regenerating variants')
                        ->body('The old variants were removed; new ones are being generated now.')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('No variants generated')
                        ->body('This image does not qualify for variants (animated GIF or below the configured minimum size).')
                        ->warning()
                        ->send();
                }
            });
    }
}
