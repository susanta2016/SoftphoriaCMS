<?php

namespace App\Modules\Music\Filament\Resources\Singles\Tables;

use App\Models\User;
use App\Modules\Music\Actions\Single\DeleteSingleAction;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Exceptions\SingleInUseException;
use App\Modules\Music\Models\Single;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SinglesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover.path')
                    ->label('')
                    ->disk(fn ($record): string => $record->cover?->disk ?? 'public')
                    ->height('auto')
                    ->extraImgAttributes(['class' => 'rounded object-cover', 'style' => 'max-width:180px;max-height:180px;']),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('release_date')
                    ->label('Release Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReleaseStatus::options()),
                TernaryFilter::make('is_featured')->label('Featured'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::deleteAction(),
                ]),
            ])
            ->toolbarActions([])
            ->defaultSort('release_date', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }

    private static function deleteAction(): Action
    {
        return Action::make('deleteSingle')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (Single $record): string => "Delete \"{$record->title}\"? This cannot be undone.")
            ->action(function (Single $record) {
                /** @var User $actor */
                $actor = Auth::user();

                try {
                    app(DeleteSingleAction::class)->handle($record, $actor);

                    Notification::make()->title('Single deleted')->success()->send();
                } catch (SingleInUseException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }
}
