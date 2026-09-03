<?php

namespace App\Filament\Resources\Roles;

use App\Actions\Role\DeleteRoleAction;
use App\Exceptions\Role\ReservedRoleException;
use App\Exceptions\Role\RoleInUseException;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Models\Role;
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
 * ADMIN-004 — full role administration (create/edit/delete roles, assign
 * permissions), completing the "Full role/permission administration is
 * ADMIN-004" forward reference in docs/ARCHITECTURE.md §12 and
 * App\Models\User's canAccessPanel() doc comment. Follows the Users
 * (ADMIN-003) resource conventions codified in docs/ARCHITECTURE.md §13.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|UnitEnum|null $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    /**
     * Reused by RolesTable's row actions. Hidden for the reserved
     * Role::ADMIN_SLUG role (deleting it would lock every administrator out
     * of /admin) — the same guard is also enforced at the domain layer by
     * DeleteRoleAction so it can't be bypassed by calling the action
     * directly (docs/ARCHITECTURE.md §13).
     */
    public static function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (Role $record): bool => $record->slug !== Role::ADMIN_SLUG)
            ->requiresConfirmation()
            ->modalDescription(fn (Role $record): string => "Delete the \"{$record->name}\" role? This cannot be undone.")
            ->action(function (Role $record): void {
                /** @var User $actor */
                $actor = Auth::user();

                try {
                    app(DeleteRoleAction::class)->handle($record, $actor);

                    Notification::make()
                        ->title('Role deleted')
                        ->success()
                        ->send();
                } catch (RoleInUseException|ReservedRoleException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
