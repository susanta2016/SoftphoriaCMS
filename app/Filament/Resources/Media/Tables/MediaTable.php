<?php

namespace App\Filament\Resources\Media\Tables;

use App\Actions\Media\DeleteMediaAction;
use App\Enums\MediaCategory;
use App\Exceptions\Media\MediaInUseException;
use App\Models\Media;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')
                    ->label('Files')
                    ->disk(fn (?Media $record): string => $record?->disk ?? 'public')
                    // A true max-width/max-height cap (not a fixed square
                    // crop) — a large original scales down to fit the list
                    // row instead of blowing it out, while a small image
                    // stays its own size rather than being upscaled, and
                    // nothing gets stretched or cropped. ->height('auto')
                    // only exists to stop ImageColumn's own default (a
                    // hardcoded 2.5rem it injects whenever ->height() isn't
                    // called) from overriding the max-height below — ->width()
                    // is deliberately left unset, since setting it would add
                    // its own competing fixed-width style.
                    ->height('auto')
                    ->extraImgAttributes(['class' => 'rounded', 'style' => 'max-width:150px;max-height:150px;'])
                    // A column-level ->visible(fn (?Media $record) ...) is
                    // evaluated once with $record = null to decide whether
                    // the column exists in the table at all, not per row —
                    // that made the whole thumbnail column vanish for every
                    // row instead of just non-image ones. Making the STATE
                    // itself null for non-image rows is what actually
                    // renders an empty cell there while the column stays.
                    ->getStateUsing(fn (Media $record): ?string => $record->category() === MediaCategory::Image ? $record->path : null),
                IconColumn::make('category_icon')
                    ->label('')
                    ->icon(fn (?Media $record): string|BackedEnum|null => $record && $record->category() !== MediaCategory::Image ? $record->category()?->getIcon() : null)
                    ->color(fn (?Media $record): ?string => $record?->category()?->getColor())
                    ->size(IconSize::TwoExtraLarge),
                TextColumn::make('alt_text')
                    ->label('Alt text')
                    ->placeholder('—')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('mime_type')
                    ->label('Category')
                    ->badge()
                    ->formatStateUsing(fn (?Media $record): string => $record?->category()?->getLabel() ?? 'Unknown')
                    ->color(fn (?Media $record): string => $record?->category()?->getColor() ?? 'gray'),
                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state))
                    ->sortable(),
                TextColumn::make('visibility')
                    ->badge()
                    ->sortable(),
                TextColumn::make('uploader.name')
                    ->label('Uploaded by')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(MediaCategory::options())
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return match ($data['value']) {
                            MediaCategory::Document->value => $query->where('mime_type', 'application/pdf'),
                            default => $query->where('mime_type', 'like', "{$data['value']}/%"),
                        };
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('View / Edit'),
                    self::deleteAction(),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->label('Actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::deleteBulkAction(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }

    /**
     * A plain Action rather than Filament's stock DeleteAction — deletion
     * must go through DeleteMediaAction so it's blocked when the file is
     * still referenced elsewhere (MediaInUseException) and so the physical
     * file/variants actually get cleaned up, not just the DB row.
     */
    private static function deleteAction(): Action
    {
        return Action::make('deleteMedia')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (Media $record): string => "Delete \"{$record->original_filename}\"? It will no longer be available for reuse.")
            ->action(function (Media $record) {
                /** @var User $actor */
                $actor = Auth::user();

                try {
                    app(DeleteMediaAction::class)->handle($record, $actor);

                    Notification::make()->title('Media deleted')->success()->send();
                } catch (MediaInUseException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    private static function deleteBulkAction(): BulkAction
    {
        return BulkAction::make('deleteSelectedMedia')
            ->label('Delete selected')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Delete the selected media files? Any still in use elsewhere will be skipped.')
            ->action(function (Collection $records) {
                /** @var User $actor */
                $actor = Auth::user();
                $action = app(DeleteMediaAction::class);

                $deleted = 0;
                $skipped = [];

                foreach ($records as $record) {
                    try {
                        $action->handle($record, $actor);
                        $deleted++;
                    } catch (MediaInUseException) {
                        $skipped[] = $record->original_filename;
                    }
                }

                if ($deleted > 0) {
                    Notification::make()
                        ->title($deleted === 1 ? '1 file deleted' : "{$deleted} files deleted")
                        ->success()
                        ->send();
                }

                if ($skipped !== []) {
                    Notification::make()
                        ->title(count($skipped) === 1 ? '1 file skipped' : count($skipped).' files skipped')
                        ->body('Still in use, so not deleted: '.implode(', ', $skipped))
                        ->warning()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }
}
