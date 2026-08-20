<?php

namespace App\Modules\Podcast\Filament\Resources\Podcasts\Tables;

use App\Models\User;
use App\Modules\Podcast\Actions\Podcast\DeletePodcastAction;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Exceptions\PodcastInUseException;
use App\Modules\Podcast\Models\Podcast;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PodcastsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('artwork.path')
                    ->label('')
                    ->disk(fn ($record): string => $record->artwork?->disk ?? 'public')
                    ->height(40)
                    ->width(40)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('episodes_count')
                    ->label('Episodes')
                    ->counts('episodes')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PodcastStatus::options()),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::deleteAction(),
                ]),
            ])
            ->toolbarActions([])
            ->defaultSort('title');
    }

    /**
     * A plain Action rather than Filament's stock DeleteAction — deletion
     * must go through DeletePodcastAction so it's blocked while the show
     * still has episodes (PodcastInUseException) rather than silently
     * orphaning them.
     */
    private static function deleteAction(): Action
    {
        return Action::make('deletePodcast')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (Podcast $record): string => "Delete \"{$record->title}\"? This cannot be undone.")
            ->action(function (Podcast $record) {
                /** @var User $actor */
                $actor = Auth::user();

                try {
                    app(DeletePodcastAction::class)->handle($record, $actor);

                    Notification::make()->title('Podcast deleted')->success()->send();
                } catch (PodcastInUseException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }
}
