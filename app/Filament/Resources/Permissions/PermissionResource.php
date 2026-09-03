<?php

namespace App\Filament\Resources\Permissions;

use App\Actions\Permission\DeletePermissionAction;
use App\Exceptions\Permission\PermissionInUseException;
use App\Filament\Resources\Permissions\Pages\CreatePermission;
use App\Filament\Resources\Permissions\Pages\EditPermission;
use App\Filament\Resources\Permissions\Pages\ListPermissions;
use App\Filament\Resources\Permissions\Schemas\PermissionForm;
use App\Filament\Resources\Permissions\Tables\PermissionsTable;
use App\Models\Permission;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * ADMIN-004 — permission administration. Assigning a permission to a role
 * is done from RoleResource (see RoleForm's docblock); this Resource only
 * manages the Permission catalog itself.
 */
class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|UnitEnum|null $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PermissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissions::route('/'),
            'create' => CreatePermission::route('/create'),
            'edit' => EditPermission::route('/{record}/edit'),
        ];
    }

    /**
     * Reused by PermissionsTable's row actions. The domain-layer guard in
     * DeletePermissionAction is what actually prevents deleting a permission
     * still in use — this action surfaces that as a danger notification
     * instead of an uncaught exception.
     */
    public static function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (Permission $record): string => "Delete the \"{$record->name}\" permission? This cannot be undone.")
            ->action(function (Permission $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                try {
                    app(DeletePermissionAction::class)->handle($record, $actor);

                    Notification::make()
                        ->title('Permission deleted')
                        ->success()
                        ->send();
                } catch (PermissionInUseException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
