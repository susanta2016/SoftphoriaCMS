<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\MediaCategory;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\Media\MediaPicker;
use App\Models\Role;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by Create/Edit (Filament\Resources\Resource::form()). Status is
 * only editable at creation time — after that, status transitions must go
 * through UserResource's dedicated "Change Status"/Block/Delete actions so
 * the self-protection guard and audit entry are always applied
 * (ADMIN-003 §I.1/§I.3 decisions). The Account Status card's quick actions
 * only make sense for an existing record, so that whole section is
 * edit-only.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'lg' => 12])
                    ->schema([
                        Group::make([
                            // ADMIN-006 convention (docs/ARCHITECTURE.md §14):
                            // every Media-referencing field goes through the
                            // shared MediaPicker rather than a module-specific
                            // FileUpload — this is what routes avatar uploads
                            // through StoreUploadedMediaAction and gives the
                            // same Upload New Media / Select from Media
                            // Library UX as Pages.
                            MediaPicker::make('avatar', 'Avatar', MediaCategory::Image),
                        ])->columnSpan(['default' => 12, 'lg' => 3]),

                        Group::make([
                            Section::make('User Information')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('name')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('email')
                                        ->label('Email address')
                                        ->email()
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(table: User::class, column: 'email', ignoreRecord: true),
                                    TextInput::make('profile_phone_number')
                                        ->label('Phone Number')
                                        ->tel()
                                        ->maxLength(30),
                                    Select::make('status')
                                        ->label('Initial status')
                                        ->options(UserStatus::options())
                                        ->default(UserStatus::PendingVerification->value)
                                        ->required()
                                        ->helperText('After creation, status is changed from the Block/Delete actions.')
                                        ->hidden(fn (?string $operation): bool => $operation === 'edit'),
                                ]),

                            Section::make('Profile')
                                ->columns(2)
                                ->schema([
                                    Textarea::make('profile_bio')
                                        ->label('Biography')
                                        ->columnSpanFull()
                                        ->maxLength(65535),
                                    Textarea::make('profile_address')
                                        ->label('Address')
                                        ->columnSpanFull()
                                        ->rows(2)
                                        ->maxLength(500),
                                    TextInput::make('profile_zip_code')
                                        ->label('Zip Code')
                                        ->maxLength(20),
                                ]),
                        ])->columnSpan(['default' => 12, 'lg' => 5]),

                        Group::make([
                            Section::make('Account Status')
                                ->schema([
                                    Placeholder::make('status_display')
                                        ->label('Status')
                                        ->content(fn (?User $record): string => $record
                                            ? UserStatus::from($record->status)->getLabel()
                                            : '—'),
                                    Placeholder::make('created_display')
                                        ->label('Created')
                                        ->content(fn (?User $record): string => $record?->created_at?->format('n/j/Y') ?? '—'),

                                    Actions::make([UserResource::sendPasswordResetLinkAction()->outlined()])->key('sendPasswordResetLinkActions')->fullWidth(),
                                    Actions::make([UserResource::generateNewPasswordAction()->outlined()])->key('generateNewPasswordActions')->fullWidth(),
                                    Actions::make([UserResource::forceLogoutAllSessionsAction()->outlined()])->key('forceLogoutAllSessionsActions')->fullWidth(),
                                    Actions::make([
                                        UserResource::blockAction()
                                            ->label('Deactivate User')
                                            ->modalDescription(fn (User $record): string => "Deactivate {$record->name}? They will be unable to sign in until reactivated."),
                                    ])->key('deactivateUserActions')->fullWidth(),
                                ])
                                ->visible(fn (?string $operation): bool => $operation === 'edit'),

                            Section::make('Role & Access')
                                ->schema([
                                    Select::make('role_id')
                                        ->label('Role')
                                        ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                                        ->searchable()
                                        ->preload()
                                        ->helperText(fn (?User $record): string => $record?->is(Auth::user())
                                            ? 'You cannot change your own role.'
                                            : 'Determines system permissions.')
                                        ->disabled(fn (?User $record): bool => $record?->is(Auth::user()) ?? false),
                                ]),
                        ])->columnSpan(['default' => 12, 'lg' => 4]),
                    ]),
            ]);
    }
}
