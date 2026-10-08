<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\PurgeDeletedUsersAction;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Users\Widgets\UserStatsWidget;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->purgeDeletedUsersAction(),
            CreateAction::make()
                ->label('Add User')
                ->icon(Heroicon::OutlinedPlus),
        ];
    }

    /**
     * Permanently deletes every user with the "deleted" status, after the
     * administrator types DELETE. See PurgeDeletedUsersAction for exactly
     * what is removed and what is kept.
     */
    protected function purgeDeletedUsersAction(): Action
    {
        $count = fn (): int => app(PurgeDeletedUsersAction::class)->count(Auth::user());

        return Action::make('purgeDeletedUsers')
            ->label(fn (): string => "Permanently delete deleted users ({$count()})")
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->outlined()
            ->visible(fn (): bool => $count() > 0)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalHeading(fn (): string => 'Permanently delete '.$count().' deleted '.($count() === 1 ? 'user' : 'users').'?')
            ->modalDescription('This cannot be undone. Only users with the "Deleted" status are removed. Their profile, roles, preferences, download history, blog comments, comment reports and reactions are deleted with them. Pages, posts, media and audit entries they created are kept, without an author.')
            ->modalSubmitActionLabel('Delete permanently')
            ->schema([
                TextInput::make('confirmation')
                    ->label('Type DELETE to confirm')
                    ->required()
                    ->in(['DELETE'])
                    ->validationMessages(['in' => 'Type DELETE in capital letters to confirm.'])
                    ->autocomplete('off'),
            ])
            ->action(function (): void {
                /** @var User $actor */
                $actor = Auth::user();
                $purged = app(PurgeDeletedUsersAction::class)->handle($actor);

                Notification::make()
                    ->title($purged === 1 ? '1 user permanently deleted' : "{$purged} users permanently deleted")
                    ->success()
                    ->send();
            });
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserStatsWidget::class,
        ];
    }
}
