<?php

namespace App\Filament\Resources\Users;

use App\Actions\Users\ChangeUserStatusAction;
use App\Actions\Users\ForceLogoutAllSessionsAction;
use App\Actions\Users\GenerateNewPasswordAction;
use App\Actions\Users\SendUserPasswordResetLinkAction;
use App\Enums\UserStatus;
use App\Exceptions\Users\CannotModifySelfException;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * ADMIN-003: Users & Roles Management — "Users: Manage" scope, plus basic
 * single-role assignment (wiring the existing roles/user_roles tables into
 * the Create/Edit form). Full role/permission administration — creating and
 * editing roles and their permission matrices — is ADMIN-004
 * (App\Filament\Resources\Roles\RoleResource /
 * App\Filament\Resources\Permissions\PermissionResource; see
 * docs/ARCHITECTURE.md §12 and App\Models\User::canAccessPanel()). This
 * resource still only supports single-role assignment per user, which
 * ADMIN-004 preserves rather than redesigning.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Shared between the table row actions and ViewUser's header actions.
     * The only way status may change after creation — carries the
     * self-protection guard and writes an audit_logs entry.
     */
    public static function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('Change Status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->schema([
                Select::make('status')
                    ->label('New status')
                    ->options(UserStatus::options())
                    ->required(),
            ])
            ->fillForm(fn (User $record): array => ['status' => $record->status])
            ->visible(fn (User $record): bool => ! $record->is(Auth::user()))
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => "Change the status for {$record->name}?")
            ->action(function (User $record, array $data): void {
                /** @var User $actor */
                $actor = Auth::user();

                try {
                    app(ChangeUserStatusAction::class)->handle($record, $data['status'], $actor);

                    Notification::make()
                        ->title('Status updated')
                        ->success()
                        ->send();
                } catch (CannotModifySelfException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Reuses Laravel's existing password broker (ADMIN-003 §4) — no
     * plaintext password field is ever presented to an administrator.
     */
    public static function sendPasswordResetLinkAction(): Action
    {
        return Action::make('sendPasswordResetLink')
            ->label('Send Password Reset Link')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => "Send a password reset link to {$record->email}?")
            ->action(function (User $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                app(SendUserPasswordResetLinkAction::class)->handle($record, $actor);

                Notification::make()
                    ->title('Password reset link sent')
                    ->success()
                    ->send();
            });
    }

    /**
     * "Block" and "Unblock" are the same underlying status transition
     * (active <-> suspended) via ChangeUserStatusAction, just labeled by the
     * record's current state to match the reference UI's list-row menu.
     */
    public static function blockAction(): Action
    {
        return Action::make('block')
            ->label(fn (User $record): string => $record->status === UserStatus::Suspended->value ? 'Unblock' : 'Block')
            ->icon(fn (User $record): Heroicon => $record->status === UserStatus::Suspended->value ? Heroicon::OutlinedLockOpen : Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->visible(fn (User $record): bool => ! $record->is(Auth::user()) && $record->status !== UserStatus::Deleted->value)
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => $record->status === UserStatus::Suspended->value
                ? "Restore access for {$record->name}?"
                : "Block {$record->name} from signing in?")
            ->action(function (User $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                $target = $record->status === UserStatus::Suspended->value
                    ? UserStatus::Active->value
                    : UserStatus::Suspended->value;

                try {
                    app(ChangeUserStatusAction::class)->handle($record, $target, $actor);

                    Notification::make()
                        ->title($target === UserStatus::Suspended->value ? 'User blocked' : 'User unblocked')
                        ->success()
                        ->send();
                } catch (CannotModifySelfException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * "Delete" here is the deleted status transition (ADMIN-003 §2: no hard
     * delete — see EditUser's class doc-comment), reusing ChangeUserStatusAction
     * so the self-protection guard and audit entry stay centralized.
     */
    public static function deleteUserAction(): Action
    {
        return Action::make('deleteUser')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (User $record): bool => ! $record->is(Auth::user()) && $record->status !== UserStatus::Deleted->value)
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => "Delete {$record->name}? Their account will be deactivated and access revoked.")
            ->action(function (User $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                try {
                    app(ChangeUserStatusAction::class)->handle($record, UserStatus::Deleted->value, $actor);

                    Notification::make()
                        ->title('User deleted')
                        ->success()
                        ->send();
                } catch (CannotModifySelfException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function generateNewPasswordAction(): Action
    {
        return Action::make('generateNewPassword')
            ->label('Generate New Password')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (User $record): bool => ! $record->is(Auth::user()))
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => "Generate a new password for {$record->name}? Their current password will stop working immediately and they'll be emailed a reset link.")
            ->action(function (User $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                try {
                    app(GenerateNewPasswordAction::class)->handle($record, $actor);

                    Notification::make()
                        ->title('New password generated and reset link sent')
                        ->success()
                        ->send();
                } catch (CannotModifySelfException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function forceLogoutAllSessionsAction(): Action
    {
        return Action::make('forceLogoutAllSessions')
            ->label('Force Logout All Sessions')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->visible(fn (User $record): bool => ! $record->is(Auth::user()))
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => "Sign {$record->name} out of all active sessions?")
            ->action(function (User $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                try {
                    app(ForceLogoutAllSessionsAction::class)->handle($record, $actor);

                    Notification::make()
                        ->title('All sessions revoked')
                        ->success()
                        ->send();
                } catch (CannotModifySelfException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
