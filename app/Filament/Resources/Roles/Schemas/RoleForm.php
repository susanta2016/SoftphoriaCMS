<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\Permission;
use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Role::ADMIN_SLUG's slug field is disabled here purely for UX — the real
 * guard is UpdateRoleAction's domain-layer check (docs/ARCHITECTURE.md §13:
 * "enforce the same guard at the domain layer ... so it can't be bypassed
 * by calling the Action directly").
 *
 * Permissions are assigned from the Role side only (a CheckboxList against
 * the existing role_permissions pivot, synced explicitly by
 * CreateRoleAction/UpdateRoleAction) rather than Filament's automatic
 * `->relationship()` field — same reasoning as MenuForm's items Repeater:
 * an explicit Action keeps the audit trail guaranteed on every save.
 */
class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Role')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set): void {
                                if ($operation === 'create') {
                                    $set('slug', str($state ?? '')->slug()->toString());
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: Role::class, column: 'slug', ignoreRecord: true)
                            ->helperText(fn (?Role $record): string => $record?->slug === Role::ADMIN_SLUG
                                ? 'This is the reserved administrator role — its slug cannot be changed.'
                                : 'Used by App\Models\User::canAccessPanel() when this is the reserved administrator role.')
                            ->disabled(fn (?Role $record): bool => $record?->slug === Role::ADMIN_SLUG),
                    ]),

                Section::make('Permissions')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->hiddenLabel()
                            ->options(fn (): array => Permission::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(2)
                            ->gridDirection('row'),
                    ]),
            ]);
    }
}
