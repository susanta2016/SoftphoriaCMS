<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('subscription'))
            ->columns([
                // Reads the same profile->avatar (Media) relationship as
                // UserInfolist's ImageEntry — no separate avatar storage/
                // resolution, no new Media rows created here.
                ImageColumn::make('profile.avatar.path')
                    ->label('Avatar')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(self::defaultAvatarUrl()),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn (string $state, User $record): string => self::renderNameWithProBadge($state, $record)),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserStatus::from($state)->getLabel())
                    ->color(fn (string $state): string => UserStatus::from($state)->getColor())
                    ->sortable(),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(UserStatus::options()),
                TernaryFilter::make('email_verified_at')
                    ->label('Email verified')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('email_verified_at'),
                        false: fn ($query) => $query->whereNull('email_verified_at'),
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('View Details'),
                    EditAction::make(),
                    UserResource::blockAction(),
                    UserResource::deleteUserAction(),
                    UserResource::changeStatusAction(),
                    UserResource::sendPasswordResetLinkAction(),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->label('Actions'),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }

    /**
     * Beside-the-name PRO badge — reuses User::hasActiveMembership() (which
     * itself defers to Subscription::isActive()) rather than recomputing any
     * status/period-end logic here, so the badge always tracks that single
     * business rule (including the cancel_at_period_end grace window).
     */
    public static function renderNameWithProBadge(string $state, User $record): string
    {
        $name = e($state);

        if (! $record->hasActiveMembership()) {
            return $name;
        }

        return $name.' '.view('filament.tables.columns.pro-badge')->render();
    }

    /**
     * A self-contained inline SVG (no external service, no stored asset) —
     * ImageColumn's fallback for users with no profile->avatar. Public so
     * tests can assert against the exact fallback string rather than the
     * rendered HTML (see PageMediaAndSeoTest's `MediaPicker::query()`
     * precedent for why — Livewire's HTML snapshot isn't a reliable place
     * to assert dynamically-resolved image sources from).
     */
    public static function defaultAvatarUrl(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
            .'<circle cx="20" cy="20" r="20" fill="#e5e7eb"/>'
            .'<circle cx="20" cy="16" r="7" fill="#9ca3af"/>'
            .'<path d="M6 34c0-8 6-13 14-13s14 5 14 13" fill="#9ca3af"/>'
            .'</svg>';

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }
}
